<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\DisposalRecord;
use App\Services\DisposalService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DisposalController extends Controller
{
    protected $disposalService;

    public function __construct(DisposalService $disposalService)
    {
        $this->disposalService = $disposalService;
    }

    /**
     * 获取报废记录列表
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = DisposalRecord::with(['asset', 'user', 'approver']);
            
            // 搜索条件
            if ($request->has('search') && $request->search) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('disposal_number', 'like', "%{$search}%")
                      ->orWhere('reason', 'like', "%{$search}%")
                      ->orWhere('document_number', 'like', "%{$search}%")
                      ->orWhereHas('asset', function ($q) use ($search) {
                          $q->where('asset_tag', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                      });
                });
            }
            
            // 状态筛选
            if ($request->has('status') && $request->status) {
                $query->where('status', $request->status);
            }
            
            // 报废类型筛选
            if ($request->has('disposal_type') && $request->disposal_type) {
                $query->where('disposal_type', $request->disposal_type);
            }
            
            // 日期范围筛选
            if ($request->has('start_date') && $request->start_date) {
                $query->where('disposal_date', '>=', $request->start_date);
            }
            
            if ($request->has('end_date') && $request->end_date) {
                $query->where('disposal_date', '<=', $request->end_date);
            }
            
            // 排序
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);
            
            // 分页
            $perPage = $request->get('per_page', 15);
            $records = $query->paginate($perPage);
            
            // 添加额外信息
            $records->getCollection()->transform(function ($record) {
                $record->disposal_type_label = $record->disposal_type_label;
                $record->status_label = $record->status_label;
                $record->gain_loss_type_label = $record->gain_loss_type_label;
                return $record;
            });
            
            return response()->json([
                'success' => true,
                'data' => $records,
                'message' => '获取报废记录成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取报废记录失败', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => '获取报废记录失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 创建报废申请
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // 验证数据
            $this->disposalService->validateDisposalData($request->all());
            
            // 创建报废申请
            $disposalRecord = $this->disposalService->createDisposalRequest($request->all(), $user);
            
            return response()->json([
                'success' => true,
                'data' => $disposalRecord->load(['asset', 'user']),
                'message' => '报废申请创建成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('创建报废申请失败', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
                'data' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '创建报废申请失败: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 获取单个报废记录详情
     */
    public function show(DisposalRecord $disposal): JsonResponse
    {
        try {
            $disposal->load(['asset', 'user', 'approver']);
            
            return response()->json([
                'success' => true,
                'data' => $disposal,
                'message' => '获取报废记录详情成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取报废记录详情失败', [
                'disposal_id' => $disposal->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取报废记录详情失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 更新报废申请
     */
    public function update(Request $request, DisposalRecord $disposal): JsonResponse
    {
        try {
            if ($disposal->status !== 'pending') {
                throw new \Exception('只有待审批的申请可以修改');
            }
            
            $user = Auth::user();
            
            // 只有申请人可以修改自己的申请
            if ($disposal->user_id !== $user->id) {
                throw new \Exception('只能修改自己的报废申请');
            }
            
            $updateData = $request->only([
                'disposal_type', 'disposal_date', 'disposal_amount',
                'salvage_value', 'reason', 'description', 'recipient_name',
                'recipient_contact', 'document_number', 'final_location',
                'handover_notes', 'environmental_impact'
            ]);
            
            // 重新计算损益
            if (isset($updateData['disposal_amount'])) {
                $updateData['gain_loss'] = $this->disposalService->calculateGainLoss(
                    $updateData['disposal_amount'],
                    $disposal->book_value
                );
            }
            
            $disposal->update($updateData);
            
            return response()->json([
                'success' => true,
                'data' => $disposal->fresh(['asset', 'user']),
                'message' => '报废申请更新成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('更新报废申请失败', [
                'disposal_id' => $disposal->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '更新报废申请失败: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 审批报废申请
     */
    public function approve(Request $request, DisposalRecord $disposal): JsonResponse
    {
        try {
            $user = Auth::user();
            $approvalNumber = $request->get('approval_number');
            
            $disposal = $this->disposalService->approveDisposal($disposal, $user, $approvalNumber);
            
            return response()->json([
                'success' => true,
                'data' => $disposal->load(['asset', 'user', 'approver']),
                'message' => '报废申请审批成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('审批报废申请失败', [
                'disposal_id' => $disposal->id,
                'approver_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '审批报废申请失败: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 拒绝报废申请
     */
    public function reject(Request $request, DisposalRecord $disposal): JsonResponse
    {
        try {
            $user = Auth::user();
            $reason = $request->get('reason', '');
            
            if (empty($reason)) {
                throw new \Exception('拒绝原因不能为空');
            }
            
            $disposal = $this->disposalService->rejectDisposal($disposal, $user, $reason);
            
            return response()->json([
                'success' => true,
                'data' => $disposal->load(['asset', 'user', 'approver']),
                'message' => '报废申请拒绝成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('拒绝报废申请失败', [
                'disposal_id' => $disposal->id,
                'rejector_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '拒绝报废申请失败: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 完成报废流程
     */
    public function complete(Request $request, DisposalRecord $disposal): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $disposal = $this->disposalService->completeDisposal($disposal, $user);
            
            return response()->json([
                'success' => true,
                'data' => $disposal->load(['asset', 'user', 'approver']),
                'message' => '报废流程完成成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('完成报废流程失败', [
                'disposal_id' => $disposal->id,
                'completer_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '完成报废流程失败: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 取消报废申请
     */
    public function cancel(Request $request, DisposalRecord $disposal): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // 只有申请人可以取消自己的申请
            if ($disposal->user_id !== $user->id) {
                throw new \Exception('只能取消自己的报废申请');
            }
            
            $this->disposalService->cancelDisposal($disposal, $user);
            
            return response()->json([
                'success' => true,
                'message' => '报废申请取消成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('取消报废申请失败', [
                'disposal_id' => $disposal->id,
                'canceler_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '取消报废申请失败: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 获取报废统计
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['start_date', 'end_date', 'disposal_type', 'status']);
            
            $statistics = $this->disposalService->getStatistics($filters);
            
            return response()->json([
                'success' => true,
                'data' => $statistics,
                'message' => '获取报废统计成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取报废统计失败', [
                'error' => $e->getMessage(),
                'filters' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取报废统计失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取逾期未处理的报废申请
     */
    public function overdue(Request $request): JsonResponse
    {
        try {
            $days = $request->get('days', 7);
            $overdueApplications = $this->disposalService->getOverdueApplications($days);
            
            return response()->json([
                'success' => true,
                'data' => $overdueApplications,
                'message' => '获取逾期申请成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取逾期报废申请失败', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => '获取逾期报废申请失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 获取资产报废历史
     */
    public function assetHistory(Asset $asset): JsonResponse
    {
        try {
            $history = $asset->disposalRecords()->with(['user', 'approver'])->get();
            
            return response()->json([
                'success' => true,
                'data' => $history,
                'message' => '获取资产报废历史成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('获取资产报废历史失败', [
                'asset_id' => $asset->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '获取资产报废历史失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 导出报废记录
     */
    public function export(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['start_date', 'end_date', 'disposal_type', 'status']);
            
            $records = $this->disposalService->exportRecords($filters);
            
            // 这里可以集成Excel导出功能
            // 目前先返回数据，前端可以处理导出
            
            return response()->json([
                'success' => true,
                'data' => $records,
                'message' => '导出数据准备成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('导出报废记录失败', [
                'error' => $e->getMessage(),
                'filters' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '导出报废记录失败: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * 删除报废记录（软删除）
     */
    public function destroy(DisposalRecord $disposal): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // 只有管理员或申请人才可以删除
            if ($disposal->user_id !== $user->id && !$user->is_admin) {
                throw new \Exception('没有权限删除此报废记录');
            }
            
            // 只有待审批的记录可以删除
            if ($disposal->status !== 'pending') {
                throw new \Exception('只有待审批的申请可以删除');
            }
            
            $disposal->delete();
            
            return response()->json([
                'success' => true,
                'message' => '报废记录删除成功'
            ]);
            
        } catch (\Exception $e) {
            Log::error('删除报废记录失败', [
                'disposal_id' => $disposal->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => '删除报废记录失败: ' . $e->getMessage()
            ], 400);
        }
    }
}