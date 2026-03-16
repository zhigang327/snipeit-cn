<?php

namespace App\Services;

use App\Models\MaintenanceRecord;
use App\Models\Asset;
use App\Services\WechatNotificationService;
use Illuminate\Support\Facades\Log;

class MaintenanceService
{
    private $wechatService;

    public function __construct(WechatNotificationService $wechatService = null)
    {
        $this->wechatService = $wechatService;
    }

    /**
     * 创建维修记录
     */
    public function createRecord(array $data)
    {
        $record = MaintenanceRecord::create($data);

        // 更新资产状态为维修中
        if ($record->status === 'pending' || $record->status === 'in_progress') {
            $record->markAssetAsMaintenance();
        }

        // 发送微信通知
        $this->sendMaintenanceNotification($record, 'created');

        Log::info('维修记录创建成功', ['record_id' => $record->id, 'asset_id' => $record->asset_id]);

        return $record;
    }

    /**
     * 更新维修记录
     */
    public function updateRecord(MaintenanceRecord $record, array $data)
    {
        $oldStatus = $record->status;
        $record->update($data);

        // 如果状态发生变化，更新资产状态
        if ($oldStatus !== $record->status) {
            $this->handleStatusChange($record, $oldStatus);
        }

        // 发送微信通知
        $this->sendMaintenanceNotification($record, 'updated');

        Log::info('维修记录更新成功', ['record_id' => $record->id, 'status' => $record->status]);

        return $record;
    }

    /**
     * 处理状态变化
     */
    private function handleStatusChange(MaintenanceRecord $record, string $oldStatus)
    {
        switch ($record->status) {
            case 'completed':
                $record->markAssetAsReady();
                // 如果没有设置完成日期，自动设置
                if (!$record->completed_date) {
                    $record->update(['completed_date' => now()]);
                }
                break;

            case 'cancelled':
                // 如果之前是维修状态，恢复资产状态
                if ($oldStatus === 'pending' || $oldStatus === 'in_progress') {
                    $record->asset->update(['status' => 'ready']);
                }
                break;

            case 'in_progress':
                $record->markAssetAsMaintenance();
                // 如果没有设置开始日期，自动设置
                if (!$record->start_date) {
                    $record->update(['start_date' => now()]);
                }
                break;

            case 'pending':
                $record->markAssetAsMaintenance();
                break;
        }
    }

    /**
     * 完成维修
     */
    public function completeRecord(MaintenanceRecord $record, array $data = [])
    {
        $updateData = array_merge([
            'status' => 'completed',
            'completed_date' => now(),
        ], $data);

        return $this->updateRecord($record, $updateData);
    }

    /**
     * 取消维修
     */
    public function cancelRecord(MaintenanceRecord $record, string $reason = null)
    {
        $updateData = [
            'status' => 'cancelled',
            'notes' => $record->notes . "\n[取消原因] " . ($reason ?? '用户取消'),
        ];

        return $this->updateRecord($record, $updateData);
    }

    /**
     * 分配维修人员
     */
    public function assignRecord(MaintenanceRecord $record, int $userId)
    {
        return $this->updateRecord($record, [
            'assigned_to' => $userId,
            'status' => 'in_progress',
        ]);
    }

    /**
     * 获取维修统计
     */
    public function getStatistics($startDate = null, $endDate = null)
    {
        $query = MaintenanceRecord::query();

        if ($startDate) {
            $query->whereDate('reported_date', '>=', $startDate);
        }

        if ($endDate) {
            $query->whereDate('reported_date', '<=', $endDate);
        }

        $total = $query->count();
        $byStatus = $query->groupBy('status')->selectRaw('status, count(*) as count')->get()->keyBy('status');
        $byType = $query->groupBy('type')->selectRaw('type, count(*) as count')->get()->keyBy('type');
        $byPriority = $query->groupBy('priority')->selectRaw('priority, count(*) as count')->get()->keyBy('priority');

        $completedQuery = MaintenanceRecord::where('status', 'completed');
        if ($startDate) {
            $completedQuery->whereDate('completed_date', '>=', $startDate);
        }
        if ($endDate) {
            $completedQuery->whereDate('completed_date', '<=', $endDate);
        }

        $avgDuration = $completedQuery->avg(\Illuminate\Support\Facades\DB::raw('DATEDIFF(completed_date, reported_date)'));
        $avgCost = $completedQuery->avg('actual_cost');
        $totalCost = $completedQuery->sum('actual_cost');

        return [
            'total' => $total,
            'by_status' => [
                'pending' => $byStatus['pending']->count ?? 0,
                'in_progress' => $byStatus['in_progress']->count ?? 0,
                'completed' => $byStatus['completed']->count ?? 0,
                'cancelled' => $byStatus['cancelled']->count ?? 0,
            ],
            'by_type' => [
                'hardware' => $byType['hardware']->count ?? 0,
                'software' => $byType['software']->count ?? 0,
                'network' => $byType['network']->count ?? 0,
                'other' => $byType['other']->count ?? 0,
            ],
            'by_priority' => [
                'low' => $byPriority['low']->count ?? 0,
                'medium' => $byPriority['medium']->count ?? 0,
                'high' => $byPriority['high']->count ?? 0,
                'urgent' => $byPriority['urgent']->count ?? 0,
            ],
            'avg_duration_days' => round($avgDuration, 1),
            'avg_cost' => round($avgCost, 2),
            'total_cost' => round($totalCost, 2),
            'completion_rate' => $total > 0 ? round(($byStatus['completed']->count ?? 0) / $total * 100, 1) : 0,
        ];
    }

    /**
     * 获取逾期维修记录
     */
    public function getOverdueRecords()
    {
        return MaintenanceRecord::whereIn('status', ['pending', 'in_progress'])
            ->whereDate('reported_date', '<', now()->subDays(7))
            ->with(['asset', 'reportedBy', 'assignedTo'])
            ->orderBy('priority', 'desc')
            ->orderBy('reported_date', 'asc')
            ->get();
    }

    /**
     * 获取资产维修历史
     */
    public function getAssetMaintenanceHistory($assetId)
    {
        return MaintenanceRecord::byAsset($assetId)
            ->with(['reportedBy', 'assignedTo'])
            ->orderBy('reported_date', 'desc')
            ->get();
    }

    /**
     * 发送微信通知
     */
    private function sendMaintenanceNotification(MaintenanceRecord $record, string $action)
    {
        if (!$this->wechatService) {
            return;
        }

        $asset = $record->asset;
        $notifications = config('services.wechat.notifications', []);

        // 检查是否启用了维修通知
        if (isset($notifications['maintenance']) && $notifications['maintenance']) {
            $actionText = [
                'created' => '创建',
                'updated' => '更新',
                'completed' => '完成',
                'assigned' => '分配',
            ];

            $content = "## 🔧 维修记录{$actionText[$action] ?? '更新'}通知\n\n" .
                       "> **资产名称**: {$asset->name}\n" .
                       "> **资产编号**: {$asset->asset_tag}\n" .
                       "> **维修标题**: {$record->title}\n" .
                       "> **报修人**: {$record->reportedBy->name}\n";

            if ($record->assigned_to) {
                $content .= "> **维修人员**: {$record->assignedTo->name}\n";
            }

            $content .= "> **优先级**: {$record->priority_text}\n" .
                       "> **状态**: {$record->status_text}\n" .
                       "> **报修时间**: {$record->reported_date}\n" .
                       "> **故障描述**: " . (mb_strlen($record->description) > 50 ? mb_substr($record->description, 0, 50) . '...' : $record->description) . "\n";

            $this->wechatService->sendMarkdown($content);
        }
    }
}