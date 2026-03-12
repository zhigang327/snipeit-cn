<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\InventoryRecord;
use App\Models\InventoryTask;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InventoryService
{
    /**
     * 创建盘点任务
     */
    public function createInventoryTask(array $data, User $creator)
    {
        return DB::transaction(function () use ($data, $creator) {
            // 验证数据
            $this->validateTaskData($data);
            
            // 生成任务编号
            $taskNumber = InventoryTask::generateTaskNumber();
            
            // 创建任务
            $task = InventoryTask::create([
                'task_number' => $taskNumber,
                'task_name' => $data['task_name'],
                'task_type' => $data['task_type'],
                'description' => $data['description'] ?? null,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'scheduled_start' => $data['scheduled_start'] ?? null,
                'scheduled_end' => $data['scheduled_end'] ?? null,
                'department_ids' => $data['department_ids'] ?? null,
                'category_ids' => $data['category_ids'] ?? null,
                'location_filters' => $data['location_filters'] ?? null,
                'asset_count' => 0,
                'assigned_to' => $data['assigned_to'] ?? null,
                'participant_ids' => $data['participant_ids'] ?? null,
                'status' => 'draft',
                'repeat_type' => $data['repeat_type'] ?? 'none',
                'repeat_interval' => $data['repeat_interval'] ?? 1,
                'repeat_days' => $data['repeat_days'] ?? null,
                'repeat_until' => $data['repeat_until'] ?? null,
                'create_next_on_complete' => $data['create_next_on_complete'] ?? false,
                'require_photos' => $data['require_photos'] ?? false,
                'require_gps' => $data['require_gps'] ?? false,
                'require_condition_check' => $data['require_condition_check'] ?? true,
                'allow_qr_scan' => $data['allow_qr_scan'] ?? true,
                'auto_update_location' => $data['auto_update_location'] ?? false,
                'auto_update_status' => $data['auto_update_status'] ?? false,
                'auto_assign_missing' => $data['auto_assign_missing'] ?? false,
                'require_review' => $data['require_review'] ?? false,
                'reviewer_id' => $data['reviewer_id'] ?? null,
                'notify_on_start' => $data['notify_on_start'] ?? true,
                'notify_on_complete' => $data['notify_on_complete'] ?? true,
                'notify_on_issues' => $data['notify_on_issues'] ?? true,
            ]);

            // 为任务生成盘点资产列表
            $assets = $this->getAssetsForTask($data);
            $task->total_assets = $assets->count();
            $task->asset_count = $assets->count();
            $task->save();

            // 记录日志
            Log::info('创建盘点任务', [
                'task_id' => $task->id,
                'task_number' => $taskNumber,
                'task_name' => $data['task_name'],
                'asset_count' => $assets->count(),
                'creator_id' => $creator->id,
            ]);

            return $task;
        });
    }

    /**
     * 开始盘点任务
     */
    public function startInventoryTask(InventoryTask $task, User $starter)
    {
        return DB::transaction(function () use ($task, $starter) {
            if (!$task->canStart()) {
                throw new \Exception('该任务无法开始');
            }

            $task->update([
                'status' => 'in_progress',
                'started_at' => now(),
            ]);

            // 发送通知
            if ($task->notify_on_start) {
                $this->sendTaskNotification($task, 'started');
            }

            // 记录日志
            Log::info('开始盘点任务', [
                'task_id' => $task->id,
                'starter_id' => $starter->id,
            ]);

            return $task;
        });
    }

    /**
     * 完成盘点任务
     */
    public function completeInventoryTask(InventoryTask $task, User $completer, array $completionData = [])
    {
        return DB::transaction(function () use ($task, $completer, $completionData) {
            if (!$task->canComplete()) {
                throw new \Exception('该任务无法完成');
            }

            $task->update([
                'status' => 'completed',
                'completed_at' => now(),
                'completion_notes' => $completionData['notes'] ?? null,
            ]);

            // 自动更新资产信息
            if ($task->auto_update_location || $task->auto_update_status || $task->auto_assign_missing) {
                $this->updateAssetsFromTask($task);
            }

            // 创建下一个重复任务
            if ($task->create_next_on_complete && $task->repeat_type !== 'none') {
                $nextTask = $task->createNextRepeatTask();
                if ($nextTask) {
                    Log::info('创建下一个重复任务', [
                        'original_task_id' => $task->id,
                        'next_task_id' => $nextTask->id,
                    ]);
                }
            }

            // 发送通知
            if ($task->notify_on_complete) {
                $this->sendTaskNotification($task, 'completed');
            }

            // 记录日志
            Log::info('完成盘点任务', [
                'task_id' => $task->id,
                'completer_id' => $completer->id,
                'completion_rate' => $task->completion_rate,
                'accuracy_rate' => $task->accuracy_rate,
            ]);

            return $task;
        });
    }

    /**
     * 创建盘点记录
     */
    public function createInventoryRecord(array $data, User $user, InventoryTask $task = null)
    {
        return DB::transaction(function () use ($data, $user, $task) {
            // 验证数据
            $this->validateRecordData($data);
            
            // 获取资产信息
            $asset = Asset::findOrFail($data['asset_id']);
            
            // 检查资产是否已经在当前任务中盘点过
            if ($task && $task->records()->where('asset_id', $asset->id)->exists()) {
                throw new \Exception('该资产已经在当前任务中盘点过');
            }

            // 生成盘点编号
            $inventoryNumber = InventoryRecord::generateInventoryNumber();
            
            // 确定状态匹配情况
            $statusMatch = $this->determineStatusMatch($asset, $data);
            
            // 确定实物状态（默认为已找到）
            $physicalStatus = $data['physical_status'] ?? 'found';
            
            // 计算是否需要有行动
            $actionTaken = $this->determineActionTaken($physicalStatus, $statusMatch, $data);
            
            // 创建盘点记录
            $record = InventoryRecord::create([
                'asset_id' => $asset->id,
                'user_id' => $user->id,
                'inventory_task_id' => $task ? $task->id : null,
                'inventory_number' => $inventoryNumber,
                'inventory_date' => $data['inventory_date'] ?? now()->toDateString(),
                'inventory_type' => $data['inventory_type'] ?? ($task ? $task->task_type : 'random'),
                'physical_status' => $physicalStatus,
                'status_match' => $statusMatch,
                'expected_location' => $asset->location,
                'actual_location' => $data['actual_location'] ?? null,
                'expected_user_id' => $asset->user_id,
                'actual_user_id' => $data['actual_user_id'] ?? null,
                'expected_status' => $asset->status,
                'actual_status' => $data['actual_status'] ?? null,
                'condition_good' => $data['condition_good'] ?? false,
                'condition_fair' => $data['condition_fair'] ?? false,
                'condition_poor' => $data['condition_poor'] ?? false,
                'needs_maintenance' => $data['needs_maintenance'] ?? false,
                'needs_replacement' => $data['needs_replacement'] ?? false,
                'notes' => $data['notes'] ?? null,
                'photos' => $data['photos'] ?? null,
                'damage_description' => $data['damage_description'] ?? null,
                'estimated_repair_cost' => $data['estimated_repair_cost'] ?? null,
                'gps_latitude' => $data['gps_latitude'] ?? null,
                'gps_longitude' => $data['gps_longitude'] ?? null,
                'scan_time' => $data['scan_time'] ?? now(),
                'qr_code_scan_result' => $data['qr_code_scan_result'] ?? null,
                'action_taken' => $actionTaken,
                'action_details' => $data['action_details'] ?? null,
                'action_by' => $actionTaken !== 'none' ? $user->id : null,
                'action_time' => $actionTaken !== 'none' ? now() : null,
                'review_status' => 'pending',
            ]);

            // 如果是任务盘点，更新任务进度
            if ($task) {
                $task->incrementCompletedAsset($record);
                
                // 如果有异常，发送通知
                if ($record->hasIssues() && $task->notify_on_issues) {
                    $this->sendIssueNotification($task, $record);
                }
            }

            // 自动更新资产信息（如果需要）
            $this->autoUpdateAssetFromRecord($record, $task);

            // 记录日志
            Log::info('创建盘点记录', [
                'record_id' => $record->id,
                'asset_id' => $asset->id,
                'user_id' => $user->id,
                'task_id' => $task ? $task->id : null,
                'physical_status' => $physicalStatus,
                'status_match' => $statusMatch,
            ]);

            return $record;
        });
    }

    /**
     * 审核盘点记录
     */
    public function reviewInventoryRecord(InventoryRecord $record, User $reviewer, array $reviewData)
    {
        return DB::transaction(function () use ($record, $reviewer, $reviewData) {
            if (!$record->needsReview()) {
                throw new \Exception('该记录无需审核');
            }

            $record->update([
                'review_status' => $reviewData['status'],
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => $reviewData['notes'] ?? null,
            ]);

            // 如果审核通过且有纠正行动，更新资产信息
            if ($reviewData['status'] === 'approved' && $record->action_taken === 'corrected') {
                $record->updateAssetFromResult();
            }

            // 记录日志
            Log::info('审核盘点记录', [
                'record_id' => $record->id,
                'reviewer_id' => $reviewer->id,
                'review_status' => $reviewData['status'],
            ]);

            return $record;
        });
    }

    /**
     * 获取任务范围内的资产
     */
    private function getAssetsForTask(array $taskData)
    {
        $query = Asset::where('status', '!=', 'disposed'); // 排除已报废的资产
        
        // 部门筛选
        if (!empty($taskData['department_ids'])) {
            $departmentIds = is_array($taskData['department_ids'])
                ? $taskData['department_ids']
                : json_decode($taskData['department_ids'], true);
            
            $query->whereIn('department_id', $departmentIds);
        }
        
        // 类别筛选
        if (!empty($taskData['category_ids'])) {
            $categoryIds = is_array($taskData['category_ids'])
                ? $taskData['category_ids']
                : json_decode($taskData['category_ids'], true);
            
            $query->whereIn('category_id', $categoryIds);
        }
        
        // 位置筛选
        if (!empty($taskData['location_filters'])) {
            $locationFilters = is_array($taskData['location_filters'])
                ? $taskData['location_filters']
                : json_decode($taskData['location_filters'], true);
            
            $query->whereIn('location', $locationFilters);
        }
        
        // 随机抽查
        if ($taskData['task_type'] === 'random') {
            $count = min($taskData['asset_count'] ?? 50, $query->count());
            return $query->inRandomOrder()->limit($count)->get();
        }
        
        // 其他类型的盘点返回所有资产
        return $query->get();
    }

    /**
     * 确定状态匹配情况
     */
    private function determineStatusMatch(Asset $asset, array $data)
    {
        $locationMatched = true;
        $userMatched = true;
        
        // 检查位置匹配
        if (isset($data['actual_location']) && $asset->location !== $data['actual_location']) {
            $locationMatched = false;
        }
        
        // 检查用户匹配
        if (isset($data['actual_user_id']) && $asset->user_id != $data['actual_user_id']) {
            $userMatched = false;
        }
        
        // 确定匹配类型
        if ($locationMatched && $userMatched) {
            return 'matched';
        } elseif (!$locationMatched && $userMatched) {
            return 'location_mismatch';
        } elseif ($locationMatched && !$userMatched) {
            return 'user_mismatch';
        } else {
            return 'both_mismatch';
        }
    }

    /**
     * 确定需要采取的行动
     */
    private function determineActionTaken($physicalStatus, $statusMatch, array $data)
    {
        // 如果资产未找到，标记为待跟进
        if ($physicalStatus === 'not_found') {
            return 'follow_up';
        }
        
        // 如果有损坏需要维修，标记为已标记
        if ($data['needs_maintenance'] ?? false || $data['needs_replacement'] ?? false) {
            return 'flagged';
        }
        
        // 如果状态不匹配，可能需要纠正
        if ($statusMatch !== 'matched') {
            return isset($data['action_taken']) ? $data['action_taken'] : 'follow_up';
        }
        
        // 其他情况无行动
        return 'none';
    }

    /**
     * 根据盘点记录自动更新资产信息
     */
    private function autoUpdateAssetFromRecord(InventoryRecord $record, InventoryTask $task = null)
    {
        $shouldUpdate = false;
        
        // 检查是否需要自动更新
        if ($task) {
            $shouldUpdate = $task->auto_update_location || $task->auto_update_status || $task->auto_assign_missing;
        }
        
        if (!$shouldUpdate) {
            return;
        }
        
        $asset = $record->asset;
        $updates = [];
        
        // 自动更新位置
        if ($task->auto_update_location && $record->actual_location && $asset->location !== $record->actual_location) {
            $updates['location'] = $record->actual_location;
        }
        
        // 自动更新用户分配
        if ($task->auto_assign_missing && $record->actual_user_id && $asset->user_id !== $record->actual_user_id) {
            $updates['user_id'] = $record->actual_user_id;
        }
        
        // 自动更新状态
        if ($task->auto_update_status && $record->actual_status && $asset->status !== $record->actual_status) {
            $updates['status'] = $record->actual_status;
        }
        
        if (!empty($updates)) {
            $asset->update($updates);
            Log::info('自动更新资产信息', [
                'asset_id' => $asset->id,
                'updates' => $updates,
                'record_id' => $record->id,
            ]);
        }
    }

    /**
     * 更新资产信息（任务完成时批量更新）
     */
    private function updateAssetsFromTask(InventoryTask $task)
    {
        $records = $task->records()->where('physical_status', 'found')->get();
        
        foreach ($records as $record) {
            try {
                $record->updateAssetFromResult();
            } catch (\Exception $e) {
                Log::error('更新资产信息失败', [
                    'record_id' => $record->id,
                    'asset_id' => $record->asset_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 发送任务通知
     */
    private function sendTaskNotification(InventoryTask $task, string $event)
    {
        // TODO: 实现通知发送逻辑
        // 可以集成微信通知、邮件通知等
        
        Log::info('发送盘点任务通知', [
            'task_id' => $task->id,
            'event' => $event,
            'recipients' => $task->participant_ids,
        ]);
    }

    /**
     * 发送异常通知
     */
    private function sendIssueNotification(InventoryTask $task, InventoryRecord $record)
    {
        // TODO: 实现异常通知发送逻辑
        
        Log::info('发送盘点异常通知', [
            'task_id' => $task->id,
            'record_id' => $record->id,
            'asset_id' => $record->asset_id,
            'issues' => $record->issue_description,
        ]);
    }

    /**
     * 验证任务数据
     */
    private function validateTaskData(array $data)
    {
        $rules = [
            'task_name' => 'required|string|max:255',
            'task_type' => 'required|in:periodic,random,full,spot,cycle',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ];
        
        $validator = \Validator::make($data, $rules);
        
        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }
    }

    /**
     * 验证记录数据
     */
    private function validateRecordData(array $data)
    {
        $rules = [
            'asset_id' => 'required|exists:assets,id',
            'physical_status' => 'in:found,not_found,damaged,scrapped,transferred',
            'actual_location' => 'nullable|string|max:255',
            'actual_user_id' => 'nullable|exists:users,id',
            'actual_status' => 'nullable|in:available,assigned,maintenance,disposed',
        ];
        
        $validator = \Validator::make($data, $rules);
        
        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }
    }

    /**
     * 获取盘点统计信息
     */
    public function getInventoryStatistics(array $filters = [])
    {
        $query = InventoryRecord::query();
        
        // 应用过滤器
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
        
        $totalCount = $query->count();
        $foundCount = $query->clone()->where('physical_status', 'found')->count();
        $matchedCount = $query->clone()->where('status_match', 'matched')->count();
        $issueCount = $query->clone()->where(function ($q) {
            $q->where('physical_status', '!=', 'found')
              ->orWhere('status_match', '!=', 'matched')
              ->orWhere('condition_poor', true)
              ->orWhere('needs_maintenance', true)
              ->orWhere('needs_replacement', true);
        })->count();
        
        // 按类型统计
        $typeStats = InventoryRecord::select('inventory_type')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(CASE WHEN physical_status = "found" THEN 1 ELSE 0 END) as found')
            ->selectRaw('SUM(CASE WHEN status_match = "matched" THEN 1 ELSE 0 END) as matched')
            ->groupBy('inventory_type')
            ->get();
        
        // 按状态统计
        $statusStats = InventoryRecord::select('physical_status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('physical_status')
            ->get();
        
        // 按匹配情况统计
        $matchStats = InventoryRecord::select('status_match')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status_match')
            ->get();
        
        return [
            'summary' => [
                'total' => $totalCount,
                'found' => $foundCount,
                'found_rate' => $totalCount > 0 ? round(($foundCount / $totalCount) * 100, 2) : 0,
                'matched' => $matchedCount,
                'match_rate' => $foundCount > 0 ? round(($matchedCount / $foundCount) * 100, 2) : 0,
                'with_issues' => $issueCount,
                'issue_rate' => $totalCount > 0 ? round(($issueCount / $totalCount) * 100, 2) : 0,
            ],
            'by_type' => $typeStats->map(function ($item) {
                return [
                    'type' => $item->inventory_type,
                    'type_label' => InventoryRecord::getInventoryTypeLabels()[$item->inventory_type] ?? $item->inventory_type,
                    'count' => $item->count,
                    'found' => $item->found,
                    'matched' => $item->matched,
                ];
            }),
            'by_status' => $statusStats->map(function ($item) {
                return [
                    'status' => $item->physical_status,
                    'status_label' => InventoryRecord::getPhysicalStatusLabels()[$item->physical_status] ?? $item->physical_status,
                    'count' => $item->count,
                ];
            }),
            'by_match' => $matchStats->map(function ($item) {
                return [
                    'match' => $item->status_match,
                    'match_label' => InventoryRecord::getStatusMatchLabels()[$item->status_match] ?? $item->status_match,
                    'count' => $item->count,
                ];
            }),
        ];
    }

    /**
     * 获取资产盘点历史
     */
    public function getAssetInventoryHistory($assetId, array $filters = [])
    {
        $query = InventoryRecord::where('asset_id', $assetId)
            ->with(['user', 'task', 'expectedUser', 'actualUser']);
        
        if (!empty($filters['start_date'])) {
            $query->where('inventory_date', '>=', $filters['start_date']);
        }
        
        if (!empty($filters['end_date'])) {
            $query->where('inventory_date', '<=', $filters['end_date']);
        }
        
        if (!empty($filters['inventory_type'])) {
            $query->where('inventory_type', $filters['inventory_type']);
        }
        
        return $query->orderBy('inventory_date', 'desc')->get();
    }
}