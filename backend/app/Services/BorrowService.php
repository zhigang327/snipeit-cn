<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\BorrowRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BorrowService
{
    /**
     * 创建借用申请
     */
    public function createBorrowRequest(array $data): BorrowRecord
    {
        return DB::transaction(function () use ($data) {
            // 验证资产状态
            $asset = Asset::findOrFail($data['asset_id']);
            
            if ($asset->status !== 'ready') {
                throw new \Exception('资产当前不可借用，状态为：' . $asset->status);
            }

            // 检查是否存在未完成的借用记录
            $existingBorrow = $asset->borrowRecords()
                ->whereIn('status', ['pending', 'approved', 'borrowed'])
                ->first();
            
            if ($existingBorrow) {
                throw new \Exception('资产已有未完成的借用记录，状态：' . $existingBorrow->status_text);
            }

            // 验证借用日期
            $borrowDate = Carbon::parse($data['borrow_date']);
            $expectedReturnDate = Carbon::parse($data['expected_return_date']);
            
            if ($borrowDate->isPast() && !$borrowDate->isToday()) {
                throw new \Exception('借用日期不能是过去的日期');
            }
            
            if ($expectedReturnDate->lessThanOrEqualTo($borrowDate)) {
                throw new \Exception('预计归还日期必须晚于借用日期');
            }
            
            $maxBorrowDays = 90; // 最大借用天数
            if ($expectedReturnDate->diffInDays($borrowDate) > $maxBorrowDays) {
                throw new \Exception('借用时长不能超过 ' . $maxBorrowDays . ' 天');
            }

            // 创建借用记录
            $borrowRecord = BorrowRecord::create([
                'asset_id' => $data['asset_id'],
                'borrower_id' => $data['borrower_id'],
                'borrow_purpose' => $data['borrow_purpose'],
                'borrow_date' => $borrowDate,
                'expected_return_date' => $expectedReturnDate,
                'deposit_amount' => $data['deposit_amount'] ?? 0,
                'borrow_conditions' => $data['borrow_conditions'] ?? null,
                'status' => 'pending',
            ]);

            return $borrowRecord;
        });
    }

    /**
     * 审批借用申请
     */
    public function approveBorrowRequest(int $borrowId, int $approverId, array $data = []): BorrowRecord
    {
        return DB::transaction(function () use ($borrowId, $approverId, $data) {
            $borrowRecord = BorrowRecord::findOrFail($borrowId);
            
            if ($borrowRecord->status !== 'pending') {
                throw new \Exception('只有待审批的借用申请可以进行审批');
            }

            $borrowRecord->update([
                'status' => 'approved',
                'approver_id' => $approverId,
            ]);

            return $borrowRecord->fresh();
        });
    }

    /**
     * 拒绝借用申请
     */
    public function rejectBorrowRequest(int $borrowId, int $approverId, string $reason): BorrowRecord
    {
        return DB::transaction(function () use ($borrowId, $approverId, $reason) {
            $borrowRecord = BorrowRecord::findOrFail($borrowId);
            
            if ($borrowRecord->status !== 'pending') {
                throw new \Exception('只有待审批的借用申请可以拒绝');
            }

            $borrowRecord->update([
                'status' => 'rejected',
                'approver_id' => $approverId,
                'rejection_reason' => $reason,
            ]);

            return $borrowRecord->fresh();
        });
    }

    /**
     * 确认借出资产
     */
    public function confirmBorrow(int $borrowId): BorrowRecord
    {
        return DB::transaction(function () use ($borrowId) {
            $borrowRecord = BorrowRecord::findOrFail($borrowId);
            
            if ($borrowRecord->status !== 'approved') {
                throw new \Exception('只有已批准的借用申请可以确认借出');
            }

            // 更新资产状态为已借出
            $asset = $borrowRecord->asset;
            $asset->status = 'borrowed';
            $asset->save();

            $borrowRecord->update([
                'status' => 'borrowed',
                'borrow_date' => now()->toDateString(),
            ]);

            return $borrowRecord->fresh();
        });
    }

    /**
     * 归还资产
     */
    public function returnAsset(int $borrowId, array $data): BorrowRecord
    {
        return DB::transaction(function () use ($borrowId, $data) {
            $borrowRecord = BorrowRecord::findOrFail($borrowId);
            
            if ($borrowRecord->status !== 'borrowed') {
                throw new \Exception('只有已借出的资产可以归还');
            }

            // 检查是否需要退还押金
            $depositReturned = $borrowRecord->deposit_amount > 0 
                ? ($data['deposit_returned'] ?? false)
                : true;

            // 更新资产状态为可用
            $asset = $borrowRecord->asset;
            $asset->status = 'ready';
            $asset->save();

            $updateData = [
                'status' => 'returned',
                'actual_return_date' => now()->toDateString(),
                'return_notes' => $data['return_notes'] ?? null,
                'deposit_returned' => $depositReturned,
            ];

            // 如果有损坏描述和赔偿费用
            if (isset($data['damage_description'])) {
                $updateData['damage_description'] = $data['damage_description'];
                $updateData['damage_fee'] = $data['damage_fee'] ?? 0;
                $updateData['damage_resolved'] = $data['damage_resolved'] ?? false;
            }

            $borrowRecord->update($updateData);

            return $borrowRecord->fresh();
        });
    }

    /**
     * 取消借用申请
     */
    public function cancelBorrowRequest(int $borrowId, string $reason = null): BorrowRecord
    {
        return DB::transaction(function () use ($borrowId, $reason) {
            $borrowRecord = BorrowRecord::findOrFail($borrowId);
            
            if (!in_array($borrowRecord->status, ['pending', 'approved'])) {
                throw new \Exception('只有待审批或已批准的借用申请可以取消');
            }

            $borrowRecord->update([
                'status' => 'cancelled',
                'rejection_reason' => $reason,
            ]);

            return $borrowRecord->fresh();
        });
    }

    /**
     * 标记为逾期
     */
    public function markAsOverdue(int $borrowId): BorrowRecord
    {
        $borrowRecord = BorrowRecord::findOrFail($borrowId);
        
        if ($borrowRecord->status !== 'borrowed') {
            throw new \Exception('只有已借出的资产可以标记为逾期');
        }

        if ($borrowRecord->expected_return_date >= now()->toDateString()) {
            throw new \Exception('借用记录尚未到期，不能标记为逾期');
        }

        $borrowRecord->update([
            'status' => 'overdue',
        ]);

        return $borrowRecord->fresh();
    }

    /**
     * 获取统计信息
     */
    public function getStatistics(): array
    {
        $total = BorrowRecord::count();
        $pending = BorrowRecord::where('status', 'pending')->count();
        $borrowed = BorrowRecord::where('status', 'borrowed')->count();
        $overdue = BorrowRecord::where('status', 'overdue')->count();
        $returned = BorrowRecord::where('status', 'returned')->count();
        
        // 计算逾期率
        $overdueRate = $borrowed > 0 ? round(($overdue / $borrowed) * 100, 2) : 0;
        
        // 平均借用时长
        $avgDuration = BorrowRecord::where('status', 'returned')
            ->selectRaw('AVG(DATEDIFF(actual_return_date, borrow_date)) as avg_days')
            ->first()
            ->avg_days ?? 0;

        // 押金统计
        $totalDeposit = BorrowRecord::sum('deposit_amount');
        $returnedDeposit = BorrowRecord::where('deposit_returned', true)->sum('deposit_amount');

        // 损坏赔偿统计
        $totalDamageFee = BorrowRecord::sum('damage_fee');
        $unresolvedDamage = BorrowRecord::where('damage_resolved', false)
            ->where('damage_fee', '>', 0)
            ->sum('damage_fee');

        return [
            'total_records' => $total,
            'pending_count' => $pending,
            'borrowed_count' => $borrowed,
            'overdue_count' => $overdue,
            'returned_count' => $returned,
            'overdue_rate' => $overdueRate,
            'avg_borrow_days' => round($avgDuration, 1),
            'total_deposit' => $totalDeposit,
            'returned_deposit' => $returnedDeposit,
            'pending_deposit' => $totalDeposit - $returnedDeposit,
            'total_damage_fee' => $totalDamageFee,
            'unresolved_damage_fee' => $unresolvedDamage,
            'resolved_damage_fee' => $totalDamageFee - $unresolvedDamage,
        ];
    }

    /**
     * 获取资产借用历史
     */
    public function getAssetHistory(int $assetId, int $limit = 10): array
    {
        $history = BorrowRecord::where('asset_id', $assetId)
            ->whereIn('status', ['returned', 'cancelled', 'rejected'])
            ->with(['borrower', 'approver'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return [
            'asset_id' => $assetId,
            'total_history' => BorrowRecord::where('asset_id', $assetId)
                ->whereIn('status', ['returned', 'cancelled', 'rejected'])
                ->count(),
            'records' => $history,
        ];
    }

    /**
     * 获取用户借用历史
     */
    public function getUserHistory(int $userId, int $limit = 10): array
    {
        $history = BorrowRecord::where('borrower_id', $userId)
            ->with(['asset', 'approver'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();

        return [
            'user_id' => $userId,
            'total_records' => BorrowRecord::where('borrower_id', $userId)->count(),
            'current_borrows' => BorrowRecord::where('borrower_id', $userId)
                ->where('status', 'borrowed')
                ->count(),
            'records' => $history,
        ];
    }

    /**
     * 检查逾期记录（每日任务）
     */
    public function checkOverdueRecords(): array
    {
        $overdueRecords = BorrowRecord::where('status', 'borrowed')
            ->whereDate('expected_return_date', '<', now()->toDateString())
            ->get();

        $updatedCount = 0;
        $records = [];

        foreach ($overdueRecords as $record) {
            $record->update(['status' => 'overdue']);
            $updatedCount++;
            $records[] = $record->id;
        }

        return [
            'checked_count' => $overdueRecords->count(),
            'updated_count' => $updatedCount,
            'record_ids' => $records,
        ];
    }

    /**
     * 发送借用提醒（即将到期）
     */
    public function getUpcomingDueRecords(int $daysBefore = 3): array
    {
        $dueDate = Carbon::today()->addDays($daysBefore)->toDateString();

        return BorrowRecord::where('status', 'borrowed')
            ->whereDate('expected_return_date', '<=', $dueDate)
            ->whereDate('expected_return_date', '>=', now()->toDateString())
            ->with(['asset', 'borrower'])
            ->orderBy('expected_return_date', 'asc')
            ->get()
            ->toArray();
    }
}