<?php

namespace App\Http\Controllers;

use App\Models\MaintenanceRecord;
use App\Models\Asset;
use App\Services\MaintenanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class MaintenanceController extends Controller
{
    private $maintenanceService;

    public function __construct(MaintenanceService $maintenanceService)
    {
        $this->maintenanceService = $maintenanceService;
    }

    /**
     * 获取维修记录列表
     */
    public function index(Request $request)
    {
        $query = MaintenanceRecord::with(['asset', 'reportedBy', 'assignedTo', 'createdBy']);

        // 搜索条件
        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->asset_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('reporter_id')) {
            $query->where('reported_by', $request->reporter_id);
        }

        if ($request->filled('assignee_id')) {
            $query->where('assigned_to', $request->assignee_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('asset', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('asset_tag', 'like', "%{$search}%");
                  });
            });
        }

        // 时间范围
        if ($request->filled('start_date')) {
            $query->whereDate('reported_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('reported_date', '<=', $request->end_date);
        }

        // 排序
        $sortField = $request->input('sort_field', 'reported_date');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortField, $sortOrder);

        // 分页
        $perPage = $request->input('per_page', 20);
        $records = $query->paginate($perPage);

        return response()->json([
            'data' => $records->items(),
            'total' => $records->total(),
            'current_page' => $records->currentPage(),
            'per_page' => $records->perPage(),
        ]);
    }

    /**
     * 创建维修记录
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'asset_id' => 'required|exists:assets,id',
            'title' => 'required|string|max:200',
            'description' => 'required|string',
            'priority' => 'required|in:low,medium,high,urgent',
            'type' => 'required|in:hardware,software,network,other',
            'reported_date' => 'required|date',
            'estimated_hours' => 'nullable|integer|min:1',
            'estimated_cost' => 'nullable|numeric|min:0',
            'parts_used' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $validated['reported_by'] = Auth::id();
        $validated['created_by'] = Auth::id();
        $validated['status'] = 'pending';

        // 验证资产状态
        $asset = Asset::findOrFail($validated['asset_id']);
        if ($asset->status === 'scrapped') {
            return response()->json(['message' => '已报废的资产不能创建维修记录'], 400);
        }

        $record = $this->maintenanceService->createRecord($validated);

        return response()->json([
            'message' => '维修记录创建成功',
            'data' => $record->load(['asset', 'reportedBy']),
        ], 201);
    }

    /**
     * 获取维修记录详情
     */
    public function show($id)
    {
        $record = MaintenanceRecord::with(['asset', 'reportedBy', 'assignedTo', 'createdBy', 'updatedBy'])
            ->findOrFail($id);

        return response()->json($record);
    }

    /**
     * 更新维修记录
     */
    public function update(Request $request, $id)
    {
        $record = MaintenanceRecord::findOrFail($id);

        // 检查权限：只有创建人、分配人或管理员可以更新
        $user = Auth::user();
        if ($record->reported_by !== $user->id && 
            $record->assigned_to !== $user->id && 
            !$user->hasRole('admin')) {
            return response()->json(['message' => '没有权限更新此维修记录'], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:200',
            'description' => 'sometimes|string',
            'diagnosis' => 'nullable|string',
            'solution' => 'nullable|string',
            'status' => 'sometimes|in:pending,in_progress,completed,cancelled',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'type' => 'sometimes|in:hardware,software,network,other',
            'assigned_to' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'completed_date' => 'nullable|date',
            'estimated_hours' => 'nullable|integer|min:1',
            'actual_hours' => 'nullable|integer|min:1',
            'estimated_cost' => 'nullable|numeric|min:0',
            'actual_cost' => 'nullable|numeric|min:0',
            'vendor' => 'nullable|string|max:100',
            'vendor_contact' => 'nullable|string|max:100',
            'parts_used' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $validated['updated_by'] = Auth::id();

        $record = $this->maintenanceService->updateRecord($record, $validated);

        return response()->json([
            'message' => '维修记录更新成功',
            'data' => $record->load(['asset', 'reportedBy', 'assignedTo']),
        ]);
    }

    /**
     * 分配维修人员
     */
    public function assign(Request $request, $id)
    {
        $record = MaintenanceRecord::findOrFail($id);

        $validated = $request->validate([
            'assigned_to' => 'required|exists:users,id',
        ]);

        $record = $this->maintenanceService->assignRecord($record, $validated['assigned_to']);

        return response()->json([
            'message' => '维修任务分配成功',
            'data' => $record->load(['asset', 'assignedTo']),
        ]);
    }

    /**
     * 完成维修
     */
    public function complete(Request $request, $id)
    {
        $record = MaintenanceRecord::findOrFail($id);

        $validated = $request->validate([
            'diagnosis' => 'required_without:solution|nullable|string',
            'solution' => 'required_without:diagnosis|nullable|string',
            'actual_hours' => 'nullable|integer|min:1',
            'actual_cost' => 'nullable|numeric|min:0',
            'vendor' => 'nullable|string|max:100',
            'vendor_contact' => 'nullable|string|max:100',
            'parts_used' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $record = $this->maintenanceService->completeRecord($record, $validated);

        return response()->json([
            'message' => '维修任务完成',
            'data' => $record->load(['asset', 'reportedBy', 'assignedTo']),
        ]);
    }

    /**
     * 取消维修
     */
    public function cancel(Request $request, $id)
    {
        $record = MaintenanceRecord::findOrFail($id);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $record = $this->maintenanceService->cancelRecord($record, $validated['reason'] ?? null);

        return response()->json([
            'message' => '维修任务已取消',
            'data' => $record->load(['asset']),
        ]);
    }

    /**
     * 删除维修记录
     */
    public function destroy($id)
    {
        $record = MaintenanceRecord::findOrFail($id);

        // 只有管理员可以删除
        if (!Auth::user()->hasRole('admin')) {
            return response()->json(['message' => '没有权限删除维修记录'], 403);
        }

        $record->delete();

        return response()->json(['message' => '维修记录已删除']);
    }

    /**
     * 获取维修统计
     */
    public function statistics(Request $request)
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        $statistics = $this->maintenanceService->getStatistics(
            $validated['start_date'] ?? null,
            $validated['end_date'] ?? null
        );

        return response()->json($statistics);
    }

    /**
     * 获取逾期维修记录
     */
    public function overdue()
    {
        $overdueRecords = $this->maintenanceService->getOverdueRecords();

        return response()->json($overdueRecords);
    }

    /**
     * 获取资产维修历史
     */
    public function assetHistory($assetId)
    {
        $history = $this->maintenanceService->getAssetMaintenanceHistory($assetId);

        return response()->json($history);
    }

    /**
     * 导出维修记录
     */
    public function export(Request $request)
    {
        $query = MaintenanceRecord::with(['asset', 'reportedBy', 'assignedTo']);

        if ($request->filled('start_date')) {
            $query->whereDate('reported_date', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('reported_date', '<=', $request->end_date);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $records = $query->orderBy('reported_date', 'desc')->get();

        $csvData = [];
        $csvData[] = ['资产名称', '资产编号', '维修标题', '报修人', '维修人员', '优先级', '状态', '报修日期', '完成日期', '维修类型', '预估费用', '实际费用'];

        foreach ($records as $record) {
            $csvData[] = [
                $record->asset->name,
                $record->asset->asset_tag,
                $record->title,
                $record->reportedBy->name,
                $record->assignedTo ? $record->assignedTo->name : '',
                $record->priority_text,
                $record->status_text,
                $record->reported_date,
                $record->completed_date,
                $record->type_text,
                $record->estimated_cost,
                $record->actual_cost,
            ];
        }

        // 这里应该生成CSV文件并返回下载链接
        // 为了简化，直接返回JSON数据
        return response()->json([
            'message' => '导出功能需实现CSV文件生成',
            'total_records' => $records->count(),
            'data' => $csvData,
        ]);
    }
}