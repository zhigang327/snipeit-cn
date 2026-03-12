<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\DepreciationRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DepreciationService
{
    /**
     * 计算直线法折旧
     * 公式: (原值 - 残值) / 使用年限
     */
    public function calculateStraightLine(Asset $asset, $period = 1)
    {
        if (!$asset->purchase_price || !$asset->salvage_value || !$asset->useful_life_years) {
            return null;
        }

        // 年折旧额 = (原值 - 残值) / 使用年限
        $annualDepreciation = ($asset->purchase_price - $asset->salvage_value) / $asset->useful_life_years;

        // 月折旧额 = 年折旧额 / 12
        $monthlyDepreciation = $annualDepreciation / 12;

        return [
            'method' => 'straight_line',
            'annual_depreciation' => round($annualDepreciation, 2),
            'monthly_depreciation' => round($monthlyDepreciation, 2),
            'accumulated_depreciation' => round($asset->accumulated_depreciation, 2),
            'book_value' => round($asset->purchase_price - $asset->accumulated_depreciation, 2),
            'depreciation_rate' => round((1 / $asset->useful_life_years) * 100, 2) . '%',
        ];
    }

    /**
     * 计算双倍余额递减法折旧
     * 公式: 账面价值 × (2 / 使用年限)
     */
    public function calculateDecliningBalance(Asset $asset)
    {
        if (!$asset->purchase_price || !$asset->useful_life_years) {
            return null;
        }

        $bookValue = $asset->current_book_value ?? $asset->purchase_price;
        $accumulatedDepreciation = $asset->accumulated_depreciation;

        // 年折旧率 = 2 / 使用年限
        $annualRate = 2 / $asset->useful_life_years;

        // 本期折旧额 = 账面价值 × 年折旧率
        $depreciationAmount = $bookValue * $annualRate;

        // 确保折旧后账面价值不低于残值
        if ($bookValue - $depreciationAmount < ($asset->salvage_value ?? 0)) {
            $depreciationAmount = $bookValue - ($asset->salvage_value ?? 0);
        }

        return [
            'method' => 'declining_balance',
            'annual_rate' => round($annualRate * 100, 2) . '%',
            'depreciation_amount' => round($depreciationAmount, 2),
            'accumulated_depreciation' => round($accumulatedDepreciation + $depreciationAmount, 2),
            'book_value' => round($bookValue - $depreciationAmount, 2),
            'salvage_value' => round($asset->salvage_value ?? 0, 2),
        ];
    }

    /**
     * 计算资产折旧
     */
    public function calculate(Asset $asset)
    {
        if (!$asset->depreciation_method) {
            return null;
        }

        switch ($asset->depreciation_method) {
            case 'straight_line':
                return $this->calculateStraightLine($asset);
            case 'declining_balance':
                return $this->calculateDecliningBalance($asset);
            default:
                return null;
        }
    }

    /**
     * 执行折旧计算并记录
     */
    public function executeDepreciation(Asset $asset, $depreciationDate = null)
    {
        if (!$asset->depreciation_method) {
            throw new \Exception('资产未设置折旧方法');
        }

        if (!$asset->purchase_price) {
            throw new \Exception('资产未设置购买价格');
        }

        $depreciationDate = $depreciationDate ?? Carbon::now();

        // 计算折旧
        $calculation = $this->calculate($asset);

        if (!$calculation) {
            throw new \Exception('折旧计算失败');
        }

        $depreciationAmount = 0;

        if ($asset->depreciation_method === 'straight_line') {
            $depreciationAmount = $calculation['monthly_depreciation'];
        } else {
            $depreciationAmount = $calculation['depreciation_amount'];
        }

        // 检查是否已经全额折旧
        if ($depreciationAmount <= 0.01) {
            throw new \Exception('资产已全额折旧,无需继续折旧');
        }

        // 使用数据库事务确保数据一致性
        DB::beginTransaction();
        try {
            // 更新资产折旧信息
            $asset->update([
                'accumulated_depreciation' => $calculation['accumulated_depreciation'],
                'current_book_value' => $calculation['book_value'],
                'last_depreciation_date' => $depreciationDate,
            ]);

            // 记录折旧历史
            DepreciationRecord::create([
                'asset_id' => $asset->id,
                'depreciation_date' => $depreciationDate,
                'depreciation_amount' => $depreciationAmount,
                'accumulated_depreciation' => $calculation['accumulated_depreciation'],
                'book_value' => $calculation['book_value'],
                'created_by' => auth()->id(),
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => '折旧计算成功',
                'data' => array_merge($calculation, [
                    'depreciation_date' => $depreciationDate->format('Y-m-d'),
                ])
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * 批量执行折旧
     */
    public function executeBatchDepreciation($assetIds, $depreciationDate = null)
    {
        $results = [];
        $successCount = 0;
        $failCount = 0;

        foreach ($assetIds as $assetId) {
            try {
                $asset = Asset::find($assetId);
                if (!$asset) {
                    $failCount++;
                    $results[] = [
                        'asset_id' => $assetId,
                        'success' => false,
                        'message' => '资产不存在'
                    ];
                    continue;
                }

                $result = $this->executeDepreciation($asset, $depreciationDate);
                $successCount++;
                $results[] = $result;
            } catch (\Exception $e) {
                $failCount++;
                $results[] = [
                    'asset_id' => $assetId,
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        return [
            'total' => count($assetIds),
            'success' => $successCount,
            'failed' => $failCount,
            'results' => $results
        ];
    }

    /**
     * 生成折旧预测表
     */
    public function generateDepreciationSchedule(Asset $asset, $years = null)
    {
        $years = $years ?? $asset->useful_life_years;

        if (!$years) {
            return null;
        }

        $schedule = [];
        $bookValue = $asset->purchase_price;
        $accumulatedDepreciation = 0;

        for ($year = 1; $year <= $years; $year++) {
            if ($asset->depreciation_method === 'straight_line') {
                // 直线法每年折旧额相同
                $annualDepreciation = ($asset->purchase_price - ($asset->salvage_value ?? 0)) / $years;
                $accumulatedDepreciation += $annualDepreciation;
                $bookValue = $asset->purchase_price - $accumulatedDepreciation;

                $schedule[] = [
                    'year' => $year,
                    'depreciation_amount' => round($annualDepreciation, 2),
                    'accumulated_depreciation' => round($accumulatedDepreciation, 2),
                    'book_value' => round(max($bookValue, $asset->salvage_value ?? 0), 2),
                ];
            } elseif ($asset->depreciation_method === 'declining_balance') {
                // 双倍余额递减法
                $annualRate = 2 / $asset->useful_life_years;
                $depreciationAmount = $bookValue * $annualRate;

                // 最后一年调整
                if ($year === $years) {
                    $depreciationAmount = $bookValue - ($asset->salvage_value ?? 0);
                }

                $accumulatedDepreciation += $depreciationAmount;
                $bookValue -= $depreciationAmount;

                $schedule[] = [
                    'year' => $year,
                    'depreciation_amount' => round($depreciationAmount, 2),
                    'accumulated_depreciation' => round($accumulatedDepreciation, 2),
                    'book_value' => round(max($bookValue, $asset->salvage_value ?? 0), 2),
                ];
            }
        }

        return $schedule;
    }

    /**
     * 获取资产折旧状态
     */
    public function getDepreciationStatus(Asset $asset)
    {
        if (!$asset->depreciation_method) {
            return [
                'status' => 'not_configured',
                'message' => '未配置折旧'
            ];
        }

        if (!$asset->purchase_price) {
            return [
                'status' => 'no_price',
                'message' => '未设置购买价格'
            ];
        }

        $isFullyDepreciated = ($asset->accumulated_depreciation + $asset->salvage_value) >= $asset->purchase_price;

        if ($isFullyDepreciated) {
            return [
                'status' => 'fully_depreciated',
                'message' => '已全额折旧',
                'progress' => 100
            ];
        }

        // 计算折旧进度
        $totalDepreciation = $asset->purchase_price - ($asset->salvage_value ?? 0);
        $progress = ($asset->accumulated_depreciation / $totalDepreciation) * 100;

        return [
            'status' => 'in_progress',
            'message' => '折旧进行中',
            'progress' => round($progress, 2)
        ];
    }
}
