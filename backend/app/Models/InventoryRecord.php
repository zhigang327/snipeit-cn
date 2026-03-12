<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class InventoryRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_id',
        'user_id',
        'inventory_task_id',
        'inventory_number',
        'inventory_date',
        'inventory_type',
        'physical_status',
        'status_match',
        'expected_location',
        'actual_location',
        'expected_user_id',
        'actual_user_id',
        'expected_status',
        'actual_status',
        'condition_good',
        'condition_fair',
        'condition_poor',
        'needs_maintenance',
        'needs_replacement',
        'notes',
        'photos',
        'damage_description',
        'estimated_repair_cost',
        'gps_latitude',
        'gps_longitude',
        'scan_time',
        'qr_code_scan_result',
        'action_taken',
        'action_details',
        'action_by',
        'action_time',
        'review_status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'inventory_date' => 'date',
        'photos' => 'array',
        'estimated_repair_cost' => 'decimal:2',
        'scan_time' => 'datetime',
        'action_time' => 'datetime',
        'reviewed_at' => 'datetime',
        'condition_good' => 'boolean',
        'condition_fair' => 'boolean',
        'condition_poor' => 'boolean',
        'needs_maintenance' => 'boolean',
        'needs_replacement' => 'boolean',
    ];

    /**
     * 关联资产
     */
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * 关联盘点员
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联盘点任务
     */
    public function task()
    {
        return $this->belongsTo(InventoryTask::class, 'inventory_task_id');
    }

    /**
     * 关联预期用户
     */
    public function expectedUser()
    {
        return $this->belongsTo(User::class, 'expected_user_id');
    }

    /**
     * 关联实际用户
     */
    public function actualUser()
    {
        return $this->belongsTo(User::class, 'actual_user_id');
    }

    /**
     * 关联行动执行人
     */
    public function actionByUser()
    {
        return $this->belongsTo(User::class, 'action_by');
    }

    /**
     * 关联审核人
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * 盘点类型映射
     */
    public static function getInventoryTypeLabels()
    {
        return [
            'periodic' => '定期盘点',
            'random' => '随机抽查',
            'full' => '全面盘点',
            'spot' => '现场抽查',
            'cycle' => '循环盘点',
        ];
    }

    /**
     * 实物状态映射
     */
    public static function getPhysicalStatusLabels()
    {
        return [
            'found' => '已找到',
            'not_found' => '未找到',
            'damaged' => '已损坏',
            'scrapped' => '已报废',
            'transferred' => '已转移',
        ];
    }

    /**
     * 状态匹配映射
     */
    public static function getStatusMatchLabels()
    {
        return [
            'matched' => '匹配',
            'location_mismatch' => '位置不匹配',
            'user_mismatch' => '用户不匹配',
            'both_mismatch' => '两者都不匹配',
        ];
    }

    /**
     * 行动映射
     */
    public static function getActionTakenLabels()
    {
        return [
            'none' => '无',
            'corrected' => '已纠正',
            'flagged' => '已标记',
            'follow_up' => '待跟进',
            'adjusted' => '已调整',
        ];
    }

    /**
     * 审核状态映射
     */
    public static function getReviewStatusLabels()
    {
        return [
            'pending' => '待审核',
            'approved' => '已通过',
            'rejected' => '已拒绝',
        ];
    }

    /**
     * 资产状态映射
     */
    public static function getAssetStatusLabels()
    {
        return [
            'available' => '可用',
            'assigned' => '已分配',
            'maintenance' => '维修中',
            'disposed' => '已报废',
        ];
    }

    /**
     * 获取盘点类型标签
     */
    public function getInventoryTypeLabelAttribute()
    {
        return self::getInventoryTypeLabels()[$this->inventory_type] ?? $this->inventory_type;
    }

    /**
     * 获取实物状态标签
     */
    public function getPhysicalStatusLabelAttribute()
    {
        return self::getPhysicalStatusLabels()[$this->physical_status] ?? $this->physical_status;
    }

    /**
     * 获取状态匹配标签
     */
    public function getStatusMatchLabelAttribute()
    {
        return self::getStatusMatchLabels()[$this->status_match] ?? $this->status_match;
    }

    /**
     * 获取行动标签
     */
    public function getActionTakenLabelAttribute()
    {
        return self::getActionTakenLabels()[$this->action_taken] ?? $this->action_taken;
    }

    /**
     * 获取审核状态标签
     */
    public function getReviewStatusLabelAttribute()
    {
        return self::getReviewStatusLabels()[$this->review_status] ?? $this->review_status;
    }

    /**
     * 获取资产状态标签
     */
    public function getExpectedStatusLabelAttribute()
    {
        return self::getAssetStatusLabels()[$this->expected_status] ?? $this->expected_status;
    }

    public function getActualStatusLabelAttribute()
    {
        return self::getAssetStatusLabels()[$this->actual_status] ?? $this->actual_status;
    }

    /**
     * 生成盘点编号
     */
    public static function generateInventoryNumber()
    {
        $date = now()->format('Ymd');
        $count = self::whereDate('created_at', today())->count() + 1;
        return 'INV-' . $date . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    /**
     * 检查是否需要审核
     */
    public function needsReview()
    {
        return $this->review_status === 'pending';
    }

    /**
     * 检查是否已审核
     */
    public function isReviewed()
    {
        return $this->review_status === 'approved';
    }

    /**
     * 检查是否已拒绝
     */
    public function isRejected()
    {
        return $this->review_status === 'rejected';
    }

    /**
     * 获取状况描述
     */
    public function getConditionDescriptionAttribute()
    {
        if ($this->condition_good) return '良好';
        if ($this->condition_fair) return '一般';
        if ($this->condition_poor) return '差';
        return '未检查';
    }

    /**
     * 检查是否有异常
     */
    public function hasIssues()
    {
        return $this->physical_status !== 'found' || 
               $this->status_match !== 'matched' ||
               $this->condition_poor ||
               $this->needs_maintenance ||
               $this->needs_replacement;
    }

    /**
     * 获取异常描述
     */
    public function getIssueDescriptionAttribute()
    {
        $issues = [];
        
        if ($this->physical_status !== 'found') {
            $issues[] = '资产' . $this->physical_status_label;
        }
        
        if ($this->status_match !== 'matched') {
            $issues[] = $this->status_match_label;
        }
        
        if ($this->condition_poor) {
            $issues[] = '状况差';
        }
        
        if ($this->needs_maintenance) {
            $issues[] = '需要维修';
        }
        
        if ($this->needs_replacement) {
            $issues[] = '需要更换';
        }
        
        return empty($issues) ? '正常' : implode(', ', $issues);
    }

    /**
     * 范围查询：有待审核的盘点记录
     */
    public function scopePendingReview($query)
    {
        return $query->where('review_status', 'pending');
    }

    /**
     * 范围查询：有异常的盘点记录
     */
    public function scopeWithIssues($query)
    {
        return $query->where(function ($q) {
            $q->where('physical_status', '!=', 'found')
              ->orWhere('status_match', '!=', 'matched')
              ->orWhere('condition_poor', true)
              ->orWhere('needs_maintenance', true)
              ->orWhere('needs_replacement', true);
        });
    }

    /**
     * 范围查询：未找到的资产
     */
    public function scopeNotFound($query)
    {
        return $query->where('physical_status', 'not_found');
    }

    /**
     * 范围查询：状态不匹配的资产
     */
    public function scopeMismatched($query)
    {
        return $query->where('status_match', '!=', 'matched');
    }

    /**
     * 范围查询：特定盘点任务
     */
    public function scopeForTask($query, $taskId)
    {
        return $query->where('inventory_task_id', $taskId);
    }

    /**
     * 范围查询：特定盘点员
     */
    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * 范围查询：按日期范围
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('inventory_date', [$startDate, $endDate]);
    }

    /**
     * 获取盘点统计信息
     */
    public static function getStatistics($filters = [])
    {
        $query = self::query();
        
        if (!empty($filters['start_date'])) {
            $query->where('inventory_date', '>=', $filters['start_date']);
        }
        
        if (!empty($filters['end_date'])) {
            $query->where('inventory_date', '<=', $filters['end_date']);
        }
        
        if (!empty($filters['inventory_type'])) {
            $query->where('inventory_type', $filters['inventory_type']);
        }
        
        $total = $query->count();
        $found = $query->where('physical_status', 'found')->count();
        $notFound = $query->where('physical_status', 'not_found')->count();
        $matched = $query->where('status_match', 'matched')->count();
        $withIssues = $query->clone()->withIssues()->count();
        
        return [
            'total' => $total,
            'found' => $found,
            'not_found' => $notFound,
            'matched' => $matched,
            'with_issues' => $withIssues,
            'found_rate' => $total > 0 ? round(($found / $total) * 100, 2) : 0,
            'match_rate' => $total > 0 ? round(($matched / $total) * 100, 2) : 0,
        ];
    }

    /**
     * 获取照片路径列表
     */
    public function getPhotoPaths()
    {
        if (empty($this->photos)) {
            return [];
        }
        
        return is_array($this->photos) ? $this->photos : json_decode($this->photos, true);
    }

    /**
     * 添加照片路径
     */
    public function addPhotoPath($path)
    {
        $photos = $this->getPhotoPaths();
        $photos[] = $path;
        $this->photos = $photos;
    }

    /**
     * 根据盘点结果更新资产信息
     */
    public function updateAssetFromResult()
    {
        if ($this->physical_status === 'found' && $this->action_taken === 'corrected') {
            $asset = $this->asset;
            
            if ($asset) {
                $updates = [];
                
                // 更新位置
                if ($this->actual_location && $asset->location !== $this->actual_location) {
                    $updates['location'] = $this->actual_location;
                }
                
                // 更新用户分配
                if ($this->actual_user_id && $asset->user_id !== $this->actual_user_id) {
                    $updates['user_id'] = $this->actual_user_id;
                }
                
                // 更新状态
                if ($this->actual_status && $asset->status !== $this->actual_status) {
                    $updates['status'] = $this->actual_status;
                }
                
                // 记录需要维修
                if ($this->needs_maintenance && $asset->status !== 'maintenance') {
                    $updates['status'] = 'maintenance';
                }
                
                if (!empty($updates)) {
                    $asset->update($updates);
                }
            }
        }
    }
}