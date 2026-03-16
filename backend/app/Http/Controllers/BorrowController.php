<?php

namespace App\Http\Controllers;

use App\Models\BorrowRecord;
use App\Services\BorrowService;
use App\Services\WechatNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class BorrowController extends Controller
{
    protected $borrowService;
    protected $wechatService;

    public function __construct(BorrowService $borrowService, WechatNotificationService $wechatService)
    {
        $this->borrowService = $borrowService;
        $this->wechatService = $wechatService;
    }

    /**
     * 获取借用记录列表
     */
    public function index(Request $request)
    {
        $query = BorrowRecord::query()->with(['asset', 'borrower', 'approver']);

        // 状态筛选
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // 借用人筛选
        if ($request->filled('borrower_id')) {
            $query->where('borrower_id', $request->borrower_id);
        }

        // 资产筛选
        if ($request->filled('asset_id')) {
            $query->where('asset_id', $request->asset_id);
        }

        // 日期范围筛选（filled 会同时检查参数存在且非空字符串）
        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        
        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // 排序
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // 分页
        $perPage = $request->get('per_page', 20);
        $records = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $records->items(),
            'pagination' => [
                'total' => $records->total(),
                'per_page' => $records->perPage(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
            ],
        ]);
    }

    /**
     * 获取单个借用记录
     */
    public function show($id)
    {
        $record = BorrowRecord::with(['asset', 'borrower', 'approver'])->findOrFail($id);
        
        return response()->json([
            'success' => true,
            'data' => $record,
        ]);
    }

    /**
     * 创建借用申请
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'asset_id' => 'required|exists:assets,id',
            'borrow_purpose' => 'required|string|max:500',
            'borrow_date' => 'required|date|after_or_equal:today',
            'expected_return_date' => 'required|date|after:borrow_date',
            'deposit_amount' => 'nullable|numeric|min:0',
            'borrow_conditions' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $data = $request->all();
            $data['borrower_id'] = Auth::id();
            
            $record = $this->borrowService->createBorrowRequest($data);

            // 发送微信通知（借用申请已创建）
            $this->wechatService->sendMaintenanceNotification(
                '借用申请已提交',
                sprintf("资产：%s\n申请人：%s\n借用日期：%s\n预计归还：%s",
                    $record->asset->name,
                    $record->borrower->name,
                    $record->borrow_date,
                    $record->expected_return_date
                ),
                $record->asset->department_id
            );

            return response()->json([
                'success' => true,
                'message' => '借用申请已提交，等待审批',
                'data' => $record,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * 审批借用申请
     */
    public function approve($id)
    {
        try {
            $record = $this->borrowService->approveBorrowRequest($id, Auth::id());

            // 发送微信通知（借用申请已批准）
            $this->wechatService->sendMaintenanceNotification(
                '借用申请已批准',
                sprintf("资产：%s\n申请人：%s\n批准人：%s\n请及时确认借出",
                    $record->asset->name,
                    $record->borrower->name,
                    $record->approver->name
                ),
                $record->asset->department_id
            );

            return response()->json([
                'success' => true,
                'message' => '借用申请已批准',
                'data' => $record,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * 拒绝借用申请
     */
    public function reject(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $record = $this->borrowService->rejectBorrowRequest(
                $id, 
                Auth::id(), 
                $request->rejection_reason
            );

            // 发送微信通知（借用申请已拒绝）
            $this->wechatService->sendMaintenanceNotification(
                '借用申请已拒绝',
                sprintf("资产：%s\n申请人：%s\n拒绝原因：%s",
                    $record->asset->name,
                    $record->borrower->name,
                    $record->rejection_reason
                ),
                $record->asset->department_id
            );

            return response()->json([
                'success' => true,
                'message' => '借用申请已拒绝',
                'data' => $record,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * 确认借出资产
     */
    public function confirmBorrow($id)
    {
        try {
            $record = $this->borrowService->confirmBorrow($id);

            // 发送微信通知（资产已借出）
            $this->wechatService->sendMaintenanceNotification(
                '资产已借出',
                sprintf("资产：%s\n借用人：%s\n借用日期：%s\n预计归还：%s",
                    $record->asset->name,
                    $record->borrower->name,
                    $record->borrow_date,
                    $record->expected_return_date
                ),
                $record->asset->department_id
            );

            return response()->json([
                'success' => true,
                'message' => '资产借出已确认',
                'data' => $record,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * 归还资产
     */
    public function returnAsset(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'return_notes' => 'nullable|string|max:1000',
            'deposit_returned' => 'boolean',
            'damage_description' => 'nullable|string|max:1000',
            'damage_fee' => 'nullable|numeric|min:0',
            'damage_resolved' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $record = $this->borrowService->returnAsset($id, $request->all());

            // 发送微信通知（资产已归还）
            $notificationMessage = "资产：{$record->asset->name}\n借用人：{$record->borrower->name}\n实际归还日期：{$record->actual_return_date}";
            
            if ($record->damage_description) {
                $notificationMessage .= "\n损坏情况：{$record->damage_description}\n赔偿费用：{$record->damage_fee}元";
            }
            
            $this->wechatService->sendMaintenanceNotification(
                '资产已归还',
                $notificationMessage,
                $record->asset->department_id
            );

            return response()->json([
                'success' => true,
                'message' => '资产归还已确认',
                'data' => $record,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * 取消借用申请
     */
    public function cancel(Request $request, $id)
    {
        try {
            $record = $this->borrowService->cancelBorrowRequest(
                $id, 
                $request->get('reason')
            );

            // 发送微信通知（借用申请已取消）
            $this->wechatService->sendMaintenanceNotification(
                '借用申请已取消',
                sprintf("资产：%s\n申请人：%s\n取消原因：%s",
                    $record->asset->name,
                    $record->borrower->name,
                    $record->rejection_reason ?? '未说明原因'
                ),
                $record->asset->department_id
            );

            return response()->json([
                'success' => true,
                'message' => '借用申请已取消',
                'data' => $record,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * 获取统计信息
     */
    public function statistics()
    {
        try {
            $stats = $this->borrowService->getStatistics();
            
            return response()->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * 获取逾期记录
     */
    public function overdue()
    {
        $records = BorrowRecord::where('status', 'overdue')
            ->orWhere(function ($query) {
                $query->where('status', 'borrowed')
                    ->whereDate('expected_return_date', '<', now()->toDateString());
            })
            ->with(['asset', 'borrower'])
            ->orderBy('expected_return_date', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $records,
            'total_count' => $records->count(),
        ]);
    }

    /**
     * 获取资产借用历史
     */
    public function assetHistory($assetId)
    {
        try {
            $history = $this->borrowService->getAssetHistory($assetId);
            
            return response()->json([
                'success' => true,
                'data' => $history,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * 获取用户借用历史
     */
    public function userHistory($userId = null)
    {
        try {
            $userId = $userId ?? Auth::id();
            $history = $this->borrowService->getUserHistory($userId);
            
            return response()->json([
                'success' => true,
                'data' => $history,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * 导出借用记录
     */
    public function export(Request $request)
    {
        $query = BorrowRecord::query()->with(['asset', 'borrower', 'approver']);

        // 应用筛选条件
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $records = $query->orderBy('created_at', 'desc')->get();

        // 转换为CSV格式
        $csvData = [];
        $csvData[] = [
            'ID',
            '资产名称',
            '资产编号',
            '借用人',
            '借用日期',
            '预计归还日期',
            '实际归还日期',
            '借用目的',
            '状态',
            '押金金额',
            '押金是否退还',
            '损坏描述',
            '损坏赔偿',
            '损坏是否处理',
            '创建时间',
            '更新时间',
        ];

        foreach ($records as $record) {
            $csvData[] = [
                $record->id,
                $record->asset->name,
                $record->asset->asset_tag,
                $record->borrower->name,
                $record->borrow_date,
                $record->expected_return_date,
                $record->actual_return_date,
                $record->borrow_purpose,
                $record->status_text,
                $record->deposit_amount,
                $record->deposit_returned ? '是' : '否',
                $record->damage_description ?? '无',
                $record->damage_fee,
                $record->damage_resolved ? '是' : '否',
                $record->created_at,
                $record->updated_at,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $csvData,
            'total_records' => $records->count(),
        ]);
    }

    /**
     * 检查并更新逾期记录
     */
    public function checkOverdue()
    {
        try {
            $result = $this->borrowService->checkOverdueRecords();
            
            return response()->json([
                'success' => true,
                'message' => '逾期检查完成',
                'data' => $result,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * 获取即将到期记录
     */
    public function upcomingDue(Request $request)
    {
        try {
            $daysBefore = $request->get('days_before', 3);
            $records = $this->borrowService->getUpcomingDueRecords($daysBefore);
            
            return response()->json([
                'success' => true,
                'data' => $records,
                'total_count' => count($records),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}