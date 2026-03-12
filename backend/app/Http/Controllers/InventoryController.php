<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\InventoryRecord;
use App\Models\InventoryTask;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InventoryController extends Controller
{
    protected $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * 获取盘点任务列表
     */
    public function tasks(Request $request): JsonResponse
    {
        try {
            $query = InventoryTask::with(['assignee', 'reviewer']);
            
            // 搜索条件
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('task_number', 'like', "%{$search}%")
                      ->orWhere('task_name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }
            
            // 状态筛选
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }
            
            // 任务类型筛选
            if ($request->has('task_type') && $request->task_type) {
                $query->where('task_type', $request->task_type);
            }
            
            // 日期范围筛选
            if ($request->has('start_date') && $request->start_date) {
                $query->where('start_date', '>=', $request->start_date);
            }
            
            if ($request->has('end_date') && $request->end_date) {
                $query->where('end_date', '<=', $request->end_date);
            }
            
            // 负责人筛选
            if ($request->has('assigned_to') && $request->assigned_to) {
                $query->where('assigned_to', $request->assigned_to);
            }
            
            // 排序
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);
            
            // 分页
            $perPage = $request->get('per_page', 15);
            $tasks = $query->paginate($perPage);
            
            // 添加额外信息
            $tasks->getCollection()->transform(function ($task) {
                $task->task_type_label = $task->task_type_label;
                $task->status_label = $task->status_label;
                $task->repeat_type_label = $task->repeat_type_label;
                $task->is_overdue = $task->isOverdue();
                $task->is_due_today = $task->isDueToday();
                return $task;
            });
            
            return response()->json([
                'success' => true,
                'data' => $tasks,
                'message' => '获取盘点任务列表成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取盘点任务列表失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取盘点任务列表失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 创建盘点任务
     */
    public function createTask(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // 创建盘点任务
            $task = $this->inventoryService->createInventoryTask($request->all(), $user);
            
            return response()->json([
                'success' => true,
                'data' => $task->load(['assignee', 'reviewer']),
                'message' => '盘点任务创建成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('创建盘点任务失败', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '创建盘点任务失败: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 获取单个盘点任务详情
     */
    public function taskDetail(InventoryTask $task): JsonResponse
    {
        try {
            $task->load(['assignee', 'reviewer']);
            
            // 获取任务统计信息
            $statistics = $task->getTaskStatistics();
            
            // 获取参与者列表
            $participants = $task->getParticipants();
            
            // 获取部门范围
            $departments = $task->getDepartments();
            
            // 获取类别范围
            $categories = $task->getCategories();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'task' => $task,
                    'statistics' => $statistics,
                    'participants' => $participants,
                    'departments' => $departments,
                    'categories' => $categories,
                ],
                'message' => '获取盘点任务详情成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取盘点任务详情失败', [
                'task_id' => $task->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取盘点任务详情失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新盘点任务
     */
    public function updateTask(Request $request, InventoryTask $task): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // 只有草稿状态的任务可以修改
            if ($task->status !== 'draft') {
                throw new \Exception('只有草稿状态的任务可以修改');
            }
            
            $updateData = $request->only([
                'task_name', 'description', 'start_date', 'end_date',
                'scheduled_start', 'scheduled_end', 'department_ids',
                'category_ids', 'location_filters', 'assigned_to',
                'participant_ids', 'repeat_type', 'repeat_interval',
                'repeat_days', 'repeat_until', 'create_next_on_complete',
                'require_photos', 'require_gps', 'require_condition_check',
                'allow_qr_scan', 'auto_update_location', 'auto_update_status',
                'auto_assign_missing', 'require_review', 'reviewer_id',
                'notify_on_start', 'notify_on_complete', 'notify_on_issues'
            ]);
            
            $task->update($updateData);
            
            // 如果更新了筛选条件，重新计算资产数量
            if (isset($updateData['department_ids']) || 
                isset($updateData['category_ids']) || 
                isset($updateData['location_filters'])) {
                
                $assets = $this->inventoryService->getAssetsForTask($task->toArray());
                $task->total_assets = $assets->count();
                $task->asset_count = $assets->count();
                $task->save();
            }
            
            return response()->json([
                'success' => true,
                'data' => $task->fresh(['assignee', 'reviewer']),
                'message' => '盘点任务更新成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('更新盘点任务失败', [
                'task_id' => $task->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '更新盘点任务失败: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 开始盘点任务
     */
    public function startTask(Request $request, InventoryTask $task): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $task = $this->inventoryService->startInventoryTask($task, $user);
            
            return response()->json([
                'success' => true,
                'data' => $task,
                'message' => '盘点任务开始成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('开始盘点任务失败', [
                'task_id' => $task->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '开始盘点任务失败: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 完成盘点任务
     */
    public function completeTask(Request $request, InventoryTask $task): JsonResponse
    {
        try {
            $user = Auth::user();
            $completionData = $request->only(['notes']);
            
            $task = $this->inventoryService->completeInventoryTask($task, $user, $completionData);
            
            return response()->json([
                'success' => true,
                'data' => $task,
                'message' => '盘点任务完成成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('完成盘点任务失败', [
                'task_id' => $task->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '完成盘点任务失败: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 取消盘点任务
     */
    public function cancelTask(Request $request, InventoryTask $task): JsonResponse
    {
        try {
            if (!$task->canCancel()) {
                throw new \Exception('该任务无法取消');
            }
            
            $task->update([
                'status' => 'cancelled',
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $task,
                'message' => '盘点任务取消成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('取消盘点任务失败', [
                'task_id' => $task->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '取消盘点任务失败: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 获取盘点记录列表
     */
    public function records(Request $request): JsonResponse
    {
        try {
            $query = InventoryRecord::with(['asset', 'user', 'task', 'expectedUser', 'actualUser']);
            
            // 搜索条件
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('inventory_number', 'like', "%{$search}%")
                      ->orWhere('notes', 'like', "%{$search}%")
                      ->orWhereHas('asset', function ($q) use ($search) {
                          $q->where('asset_tag', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                      });
                });
            }
            
            // 状态筛选
            if ($request->has('physical_status') && $request->physical_status) {
                $query->where('physical_status', $request->physical_status);
            }
            
            if ($request->has('status_match') && $request->status_match) {
                $query->where('status_match', $request->status_match);
            }
            
            if ($request->has('review_status') && $request->review_status) {
                $query->where('review_status', $request->review_status);
            }
            
            // 盘点类型筛选
            if ($request->has('inventory_type') && $request->inventory_type) {
                $query->where('inventory_type', $request->inventory_type);
            }
            
            // 资产筛选
            if ($request->has('asset_id') && $request->asset_id) {
                $query->where('asset_id', $request->asset_id);
            }
            
            // 用户筛选
            if ($request->has('user_id') && $request->user_id) {
                $query->where('user_id', $request->user_id);
            }
            
            // 任务筛选
            if ($request->has('task_id') && $request->task_id) {
                $query->where('inventory_task_id', $request->task_id);
            }
            
            // 日期范围筛选
            if ($request->has('start_date') && $request->start_date) {
                $query->where('inventory_date', '>=', $request->start_date);
            }
            
            if ($request->has('end_date') && $request->end_date) {
                $query->where('inventory_date', '<=', $request->end_date);
            }
            
            // 排序
            $sortField = $request->get('sort_field', 'inventory_date');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);
            
            // 分页
            $perPage = $request->get('per_page', 20);
            $records = $query->paginate($perPage);
            
            // 添加额外信息
            $records->getCollection()->transform(function ($record) {
                $record->inventory_type_label = $record->inventory_type_label;
                $record->physical_status_label = $record->physical_status_label;
                $record->status_match_label = $record->status_match_label;
                $record->action_taken_label = $record->action_taken_label;
                $record->review_status_label = $record->review_status_label;
                $record->expected_status_label = $record->expected_status_label;
                $record->actual_status_label = $record->actual_status_label;
                $record->condition_description = $record->condition_description;
                $record->has_issues = $record->hasIssues();
                $record->issue_description = $record->issue_description;
                return $record;
            });
            
            return response()->json([
                'success' => true,
                'data' => $records,
                'message' => '获取盘点记录列表成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取盘点记录列表失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取盘点记录列表失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 创建盘点记录
     */
    public function createRecord(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $task = $request->has('inventory_task_id') 
                ? InventoryTask::find($request->inventory_task_id)
                : null;
            
            // 创建盘点记录
            $record = $this->inventoryService->createInventoryRecord($request->all(), $user, $task);
            
            return response()->json([
                'success' => true,
                'data' => $record->load(['asset', 'user', 'task', 'expectedUser', 'actualUser']),
                'message' => '盘点记录创建成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('创建盘点记录失败', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '创建盘点记录失败: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 获取单个盘点记录详情
     */
    public function recordDetail(InventoryRecord $record): JsonResponse
    {
        try {
            $record->load([
                'asset', 'user', 'task', 'expectedUser', 'actualUser', 
                'actionByUser', 'reviewer'
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $record,
                'message' => '获取盘点记录详情成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取盘点记录详情失败', [
                'record_id' => $record->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取盘点记录详情失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 审核盘点记录
     */
    public function reviewRecord(Request $request, InventoryRecord $record): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $reviewData = $request->validate([
                'status' => 'required|in:approved,rejected',
                'notes' => 'nullable|string',
            ]);
            
            $record = $this->inventoryService->reviewInventoryRecord($record, $user, $reviewData);
            
            return response()->json([
                'success' => true,
                'data' => $record->fresh(['reviewer']),
                'message' => '盘点记录审核成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('审核盘点记录失败', [
                'record_id' => $record->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '审核盘点记录失败: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 获取盘点统计信息
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['start_date', 'end_date', 'inventory_type', 'physical_status']);
            
            $statistics = $this->inventoryService->getInventoryStatistics($filters);
            
            return response()->json([
                'success' => true,
                'data' => $statistics,
                'message' => '获取盘点统计成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取盘点统计失败', [
                'error' => $e->getMessage(),
                'filters' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取盘点统计失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取资产盘点历史
     */
    public function assetHistory(Asset $asset): JsonResponse
    {
        try {
            $history = $this->inventoryService->getAssetInventoryHistory($asset->id);
            
            return response()->json([
                'success' => true,
                'data' => $history,
                'message' => '获取资产盘点历史成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取资产盘点历史失败', [
                'asset_id' => $asset->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取资产盘点历史失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取待审核的盘点记录
     */
    public function pendingReviews(Request $request): JsonResponse
    {
        try {
            $query = InventoryRecord::pendingReview()
                ->with(['asset', 'user', 'task']);
            
            // 分页
            $perPage = $request->get('per_page', 20);
            $records = $query->paginate($perPage);
            
            // 添加额外信息
            $records->getCollection()->transform(function ($record) {
                $record->inventory_type_label = $record->inventory_type_label;
                $record->physical_status_label = $record->physical_status_label;
                $record->status_match_label = $record->status_match_label;
                $record->has_issues = $record->hasIssues();
                return $record;
            });
            
            return response()->json([
                'success' => true,
                'data' => $records,
                'message' => '获取待审核盘点记录成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取待审核盘点记录失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取待审核盘点记录失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取有异常的盘点记录
     */
    public function issueRecords(Request $request): JsonResponse
    {
        try {
            $query = InventoryRecord::withIssues()
                ->with(['asset', 'user', 'task']);
            
            // 分页
            $perPage = $request->get('per_page', 20);
            $records = $query->paginate($perPage);
            
            // 添加额外信息
            $records->getCollection()->transform(function ($record) {
                $record->inventory_type_label = $record->inventory_type_label;
                $record->physical_status_label = $record->physical_status_label;
                $record->status_match_label = $record->status_match_label;
                $record->issue_description = $record->issue_description;
                return $record;
            });
            
            return response()->json([
                'success' => true,
                'data' => $records,
                'message' => '获取异常盘点记录成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取异常盘点记录失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取异常盘点记录失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 导出盘点数据
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['start_date', 'end_date', 'inventory_type', 'physical_status', 'status_match']);
            
            $query = InventoryRecord::with(['asset', 'user', 'task', 'expectedUser', 'actualUser']);
            
            if (!empty($filters['start_date'])) {
                $query->where('inventory_date', '>=', $filters['start_date']);
            }
            
            if (!empty($filters['end_date'])) {
                $query->where('inventory_date', '<=', $filters['end_date']);
            }
            
            if (!empty($filters['inventory_type'])) {
                $query->where('inventory_type', $filters['inventory_type']);
            }
            
            if (!empty($filters['physical_status'])) {
                $query->where('physical_status', $filters['physical_status']);
            }
            
            if (!empty($filters['status_match'])) {
                $query->where('status_match', $filters['status_match']);
            }
            
            $records = $query->orderBy('inventory_date', 'desc')->get();
            
            return response()->json([
                'success' => true,
                'data' => $records,
                'message' => '导出数据准备成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('导出盘点数据失败', [
                'error' => $e->getMessage(),
                'filters' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '导出盘点数据失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取今日待办盘点任务
     */
    public function todaysTasks(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $query = InventoryTask::where('status', 'in_progress')
                ->orWhere(function ($q) {
                    $q->where('status', 'active')
                      ->whereDate('start_date', '<=', now())
                      ->whereDate('end_date', '>=', now());
                });
            
            // 如果是普通用户，只显示分配的任务
            if (!$user->is_admin) {
                $query->where(function ($q) use ($user) {
                    $q->where('assigned_to', $user->id)
                      ->orWhereJsonContains('participant_ids', $user->id);
                });
            }
            
            $tasks = $query->with(['assignee'])
                ->orderBy('priority', 'desc')
                ->orderBy('end_date', 'asc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $tasks,
                'message' => '获取今日待办任务成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取今日待办任务失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取今日待办任务失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取逾期未完成的任务
     */
    public function overdueTasks(Request $request): JsonResponse
    {
        try {
            $query = InventoryTask::where('status', 'in_progress')
                ->where('end_date', '<', now()->toDateString());
            
            $tasks = $query->with(['assignee'])
                ->orderBy('end_date', 'asc')
                ->get();
            
            return response()->json([
                'success' => true,
                'data' => $tasks,
                'message' => '获取逾期任务成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取逾期任务失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取逾期任务失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 通过二维码扫描创建盘点记录
     */
    public function scanQrCode(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $qrCodeData = $request->get('qr_code_data');
            
            // 解析二维码数据（假设包含资产ID）
            $assetId = $this->parseQrCodeData($qrCodeData);
            
            if (!$assetId) {
                throw new \Exception('无效的二维码数据');
            }
            
            // 获取资产
            $asset = Asset::find($assetId);
            
            if (!$asset) {
                throw new \Exception('资产不存在');
            }
            
            // 创建盘点记录数据
            $recordData = [
                'asset_id' => $asset->id,
                'actual_location' => $asset->location,
                'actual_user_id' => $asset->user_id,
                'actual_status' => $asset->status,
                'qr_code_scan_result' => $qrCodeData,
                'scan_time' => now(),
            ];
            
            // 如果有任务ID，添加到记录数据中
            if ($request->has('task_id')) {
                $recordData['inventory_task_id'] = $request->task_id;
            }
            
            // 创建盘点记录
            $record = $this->inventoryService->createInventoryRecord($recordData, $user, null);
            
            return response()->json([
                'success' => true,
                'data' => $record->load(['asset']),
                'message' => '二维码扫描成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('二维码扫描失败', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'qr_code_data' => $request->get('qr_code_data')
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '二维码扫描失败: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 解析二维码数据
     */
    private function parseQrCodeData($qrCodeData)
    {
        // 这里应该实现实际的二维码解析逻辑
        // 假设二维码包含资产ID的JSON数据：{"asset_id": 123}
        
        try {
            $data = json_decode($qrCodeData, true);
            return $data['asset_id'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}