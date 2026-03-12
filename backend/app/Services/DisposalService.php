<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\DisposalRecord;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DisposalService
{
    /**
     * 创建报废申请
     */
    public function createDisposalRequest(array $data, User $user)
    {
        return DB::transaction(function () use ($data, $user) {
            // 验证资产状态
            $asset = Asset::findOrFail($data['asset_id']);
            
            if ($asset->status === 'disposed') {
                throw new \Exception('该资产已经报废，不能重复申请');
            }
            
            if ($asset->status === 'maintenance') {
                throw new \Exception('该资产正在维修中，请先完成维修');
            }
            
            if ($asset->currentBorrow) {
                throw new \Exception('该资产正在被借用，请先归还');
            }

            // 获取资产账面价值
            $bookValue = $asset->current_book_value ?? $asset->purchase_price;
            
            // 创建报废记录
            $disposalRecord = DisposalRecord::create([
                'asset_id' => $asset->id,
                'user_id' => $user->id,
                'disposal_number' => DisposalRecord::generateDisposalNumber(),
                'disposal_type' => $data['disposal_type'],
                'disposal_date' => $data['disposal_date'] ?? now(),
                'disposal_amount' => $data['disposal_amount'] ?? 0,
                'salvage_value' => $data['salvage_value'] ?? 0,
                'book_value' => $bookValue,
                'gain_loss' => $this->calculateGainLoss($data['disposal_amount'] ?? 0, $bookValue),
                'reason' => $data['reason'],
                'description' => $data['description'] ?? null,
                'recipient_name' => $data['recipient_name'] ?? null,
                'recipient_contact' => $data['recipient_contact'] ?? null,
                'document_number' => $data['document_number'] ?? null,
                'approval_number' => $data['approval_number'] ?? null,
                'status' => 'pending',
                'final_location' => $data['final_location'] ?? null,
                'handover_notes' => $data['handover_notes'] ?? null,
                'environmental_impact' => $data['environmental_impact'] ?? null,
            ]);

            // 记录日志
            Log::info('创建报废申请', [
                'disposal_id' => $disposalRecord->id,
                'asset_id' => $asset->id,
                'user_id' => $user->id,
                'disposal_type' => $data['disposal_type'],
            ]);

            return $disposalRecord;
        });
    }

    /**
     * 审批报废申请
     */
    public function approveDisposal(DisposalRecord $disposalRecord, User $approver, string $approvalNumber = null)
    {
        return DB::transaction(function () use ($disposalRecord, $approver, $approvalNumber) {
            if (!$disposalRecord->canApprove()) {
                throw new \Exception('该报废申请无法审批');
            }

            // 更新审批信息
            $disposalRecord->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
                'approved_at' => now(),
                'approval_number' => $approvalNumber ?? $disposalRecord->approval_number,
            ]);

            // 记录日志
            Log::info('审批报废申请', [
                'disposal_id' => $disposalRecord->id,
                'approver_id' => $approver->id,
            ]);

            return $disposalRecord;
        });
    }

    /**
     * 拒绝报废申请
     */
    public function rejectDisposal(DisposalRecord $disposalRecord, User $rejector, string $reason)
    {
        return DB::transaction(function () use ($disposalRecord, $rejector, $reason) {
            if (!$disposalRecord->canApprove()) {
                throw new \Exception('该报废申请无法拒绝');
            }

            $disposalRecord->update([
                'status' => 'rejected',
                'approved_by' => $rejector->id,
                'approved_at' => now(),
                'rejection_reason' => $reason,
            ]);

            // 记录日志
            Log::info('拒绝报废申请', [
                'disposal_id' => $disposalRecord->id,
                'rejector_id' => $rejector->id,
                'reason' => $reason,
            ]);

            return $disposalRecord;
        });
    }

    /**
     * 完成报废流程
     */
    public function completeDisposal(DisposalRecord $disposalRecord, User $completer)
    {
        return DB::transaction(function () use ($disposalRecord, $completer) {
            if (!$disposalRecord->canComplete()) {
                throw new \Exception('该报废申请无法完成');
            }

            // 更新资产状态为已报废
            $asset = $disposalRecord->asset;
            $asset->update([
                'status' => 'disposed',
                'user_id' => null, // 解除分配
                'department_id' => null, // 解除部门关联
                'location' => '已报废',
                'checkout_date' => null,
                'expected_checkin_date' => null,
            ]);

            // 更新报废记录状态
            $disposalRecord->update([
                'status' => 'completed',
            ]);

            // 记录日志
            Log::info('完成报废流程', [
                'disposal_id' => $disposalRecord->id,
                'asset_id' => $asset->id,
                'completer_id' => $completer->id,
            ]);

            return $disposalRecord;
        });
    }

    /**
     * 取消报废申请
     */
    public function cancelDisposal(DisposalRecord $disposalRecord, User $canceler)
    {
        return DB::transaction(function () use ($disposalRecord, $canceler) {
            if ($disposalRecord->status !== 'pending') {
                throw new \Exception('只有待审批的申请可以取消');
            }

            $disposalRecord->delete();

            // 记录日志
            Log::info('取消报废申请', [
                'disposal_id' => $disposalRecord->id,
                'canceler_id' => $canceler->id,
            ]);

            return true;
        });
    }

    /**
     * 计算处置损益
     */
    private function calculateGainLoss($disposalAmount, $bookValue)
    {
        return $disposalAmount - $bookValue;
    }

    /**
     * 获取报废统计
     */
    public function getStatistics(array $filters = [])
    {
        $query = DisposalRecord::query();

        // 应用过滤器
        if (!empty($filters['start_date'])) {
            $query->where('disposal_date', '>=', $filters['start_date']);
        }
        
        if (!empty($filters['end_date'])) {
            $query->where('disposal_date', '<=', $filters['end_date']);
        }
        
        if (!empty($filters['disposal_type'])) {
            $query->where('disposal_type', $filters['disposal_type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $totalCount = $query->count();
        $totalAmount = $query->sum('disposal_amount');
        $totalBookValue = $query->sum('book_value');
        $totalGainLoss = $query->sum('gain_loss');

        // 按类型统计
        $typeStats = $query->select('disposal_type')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(disposal_amount) as amount')
            ->selectRaw('SUM(book_value) as book_value')
            ->selectRaw('SUM(gain_loss) as gain_loss')
            ->groupBy('disposal_type')
            ->get();

        // 按状态统计
        $statusStats = $query->select('status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('status')
            ->get();

        return [
            'total' => [
                'count' => $totalCount,
                'amount' => (float)$totalAmount,
                'book_value' => (float)$totalBookValue,
                'gain_loss' => (float)$totalGainLoss,
            ],
            'by_type' => $typeStats->map(function ($item) {
                return [
                    'type' => $item->disposal_type,
                    'type_label' => DisposalRecord::getDisposalTypeLabels()[$item->disposal_type] ?? $item->disposal_type,
                    'count' => $item->count,
                    'amount' => (float)$item->amount,
                    'book_value' => (float)$item->book_value,
                    'gain_loss' => (float)$item->gain_loss,
                ];
            }),
            'by_status' => $statusStats->map(function ($item) {
                return [
                    'status' => $item->status,
                    'status_label' => DisposalRecord::getStatusLabels()[$item->status] ?? $item->status,
                    'count' => $item->count,
                ];
            }),
        ];
    }

    /**
     * 获取逾期未处理的报废申请
     */
    public function getOverdueApplications($days = 7)
    {
        $cutoffDate = now()->subDays($days);
        
        return DisposalRecord::where('status', 'pending')
            ->where('created_at', '<=', $cutoffDate)
            ->with(['asset', 'user'])
            ->get();
    }

    /**
     * 导出报废记录
     */
    public function exportRecords(array $filters = [])
    {
        $query = DisposalRecord::with(['asset', 'user', 'approver']);

        // 应用过滤器
        if (!empty($filters['start_date'])) {
            $query->where('disposal_date', '>=', $filters['start_date']);
        }
        
        if (!empty($filters['end_date'])) {
            $query->where('disposal_date', '<=', $filters['end_date']);
        }
        
        if (!empty($filters['disposal_type'])) {
            $query->where('disposal_type', $filters['disposal_type']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('disposal_date', 'desc')->get();
    }

    /**
     * 验证报废申请数据
     */
    public function validateDisposalData(array $data)
    {
        $rules = [
            'asset_id' => 'required|exists:assets,id',
            'disposal_type' => 'required|in:sold,scrapped,donated,transferred,lost',
            'disposal_date' => 'required|date',
            'reason' => 'required|string|min:10',
            'disposal_amount' => 'nullable|numeric|min:0',
            'salvage_value' => 'nullable|numeric|min:0',
        ];

        $validator = \Validator::make($data, $rules);
        
        if ($validator->fails()) {
            throw new \Exception($validator->errors()->first());
        }

        return true;
    }
}