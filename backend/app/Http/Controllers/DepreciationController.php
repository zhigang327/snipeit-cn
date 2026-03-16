<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\DepreciationRecord;
use App\Services\DepreciationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DepreciationController extends Controller
{
    private $depreciationService;

    public function __construct(DepreciationService $depreciationService)
    {
        $this->depreciationService = $depreciationService;
    }

    /**
     * 计算资产折旧
     */
    public function calculate(Request $request, Asset $asset): JsonResponse
    {
        try {
            $calculation = $this->depreciationService->calculate($asset);

            if (!$calculation) {
                return response()->json([
                    'success' => false,
                    'message' => '资产未配置折旧参数'
                ], 422);
            }

            $status = $this->depreciationService->getDepreciationStatus($asset);

            return response()->json([
                'success' => true,
                'data' => array_merge($calculation, [
                    'status' => $status,
                ])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 执行折旧计算
     */
    public function execute(Request $request, Asset $asset): JsonResponse
    {
        try {
            $validated = $request->validate([
                'depreciation_date' => 'nullable|date',
                'notes' => 'nullable|string',
            ]);

            $result = $this->depreciationService->executeDepreciation(
                $asset,
                $validated['depreciation_date'] ?? null
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * 批量执行折旧
     */
    public function executeBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'asset_ids' => 'required|array',
            'asset_ids.*' => 'exists:assets,id',
            'depreciation_date' => 'nullable|date',
        ]);

        try {
            $result = $this->depreciationService->executeBatchDepreciation(
                $validated['asset_ids'],
                $validated['depreciation_date'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => "批量折旧完成,成功{$result['success']}个,失败{$result['failed']}个",
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取资产折旧记录
     */
    public function records(Request $request, Asset $asset): JsonResponse
    {
        $records = $asset->depreciationRecords()
            ->with('creator')
            ->orderBy('depreciation_date', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success' => true,
            'data' => $records
        ]);
    }

    /**
     * 获取折旧预测表
     */
    public function schedule(Request $request, Asset $asset): JsonResponse
    {
        $years = $request->get('years');

        try {
            $schedule = $this->depreciationService->generateDepreciationSchedule($asset, $years);

            if (!$schedule) {
                return response()->json([
                    'success' => false,
                    'message' => '资产未配置折旧参数'
                ], 422);
            }

            return response()->json([
                'success' => true,
                'data' => $schedule
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取折旧报表
     */
    public function report(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'department_id' => 'nullable|exists:departments,id',
            'category_id' => 'nullable|exists:asset_categories,id',
        ]);

        $query = DepreciationRecord::query()
            ->with(['asset.category', 'asset.department', 'creator']);

        // 日期筛选
        if ($request->has('start_date')) {
            $query->where('depreciation_date', '>=', $validated['start_date']);
        }

        if ($request->has('end_date')) {
            $query->where('depreciation_date', '<=', $validated['end_date']);
        }

        // 部门筛选
        if ($request->has('department_id')) {
            $query->whereHas('asset', function ($q) use ($validated) {
                $q->where('department_id', $validated['department_id']);
            });
        }

        // 分类筛选
        if ($request->has('category_id')) {
            $query->whereHas('asset', function ($q) use ($validated) {
                $q->where('category_id', $validated['category_id']);
            });
        }

        // 按日期排序
        $query->orderBy('depreciation_date', 'desc');

        $records = $query->paginate($request->per_page ?? 20);

        // 统计信息
        $statistics = [
            'total_records' => $records->total(),
            'total_depreciation' => $query->sum('depreciation_amount'),
            'average_depreciation' => $query->avg('depreciation_amount'),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'records' => $records,
                'statistics' => $statistics
            ]
        ]);
    }

    /**
     * 获取折旧统计
     */
    public function statistics(): JsonResponse
    {
        $totalAssets = Asset::whereNotNull('depreciation_method')->count();

        $depreciatedAssets = Asset::whereNotNull('depreciation_method')
            ->where('current_book_value', '<=', DB::raw('salvage_value'))
            ->count();

        $totalPurchasePrice = Asset::whereNotNull('depreciation_method')->sum('purchase_price');
        $totalCurrentBookValue = Asset::whereNotNull('depreciation_method')->sum('current_book_value');
        $totalAccumulatedDepreciation = Asset::whereNotNull('depreciation_method')->sum('accumulated_depreciation');

        // 按折旧方法统计
        $byMethod = Asset::whereNotNull('depreciation_method')
            ->selectRaw('depreciation_method, COUNT(*) as count, SUM(purchase_price) as total_value')
            ->groupBy('depreciation_method')
            ->get()
            ->keyBy('depreciation_method');

        return response()->json([
            'success' => true,
            'data' => [
                'total_assets' => $totalAssets,
                'depreciated_assets' => $depreciatedAssets,
                'depreciating_assets' => $totalAssets - $depreciatedAssets,
                'total_purchase_price' => $totalPurchasePrice,
                'total_current_book_value' => $totalCurrentBookValue,
                'total_accumulated_depreciation' => $totalAccumulatedDepreciation,
                'by_method' => $byMethod,
            ]
        ]);
    }
}
