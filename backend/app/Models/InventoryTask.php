<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class InventoryTask extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'task_number',
        'task_name',
        'task_type',
        'description',
        'start_date',
        'end_date',
        'scheduled_start',
        'scheduled_end',
        'department_ids',
        'category_ids',
        'location_filters',
        'asset_count',
        'assigned_to',
        'participant_ids',
        'status',
        'total_assets',
        'completed_assets',
        'found_assets',
        'not_found_assets',
        'mismatched_assets',
        'flagged_assets',
        'completion_rate',
        'accuracy_rate',
        'notify_on_start',
        'notify_on_complete',
        'notify_on_issues',
        'repeat_type',
        'repeat_interval',
        'repeat_days',
        'repeat_until',
        'create_next_on_complete',
        'require_photos',
        'require_gps',
        'require_condition_check',
        'allow_qr_scan',
        'auto_update_location',
        'auto_update_status',
        'auto_assign_missing',
        'require_review',
        'reviewer_id',
        'started_at',
        'completed_at',
        'completion_notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'department_ids' => 'array',
        'category_ids' => 'array',
        'location_filters' => 'array',
        'repeat_days' => 'array',
        'repeat_until' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'notify_on_start' => 'boolean',
        'notify_on_complete' => 'boolean',
        'notify_on_issues' => 'boolean',
        'require_photos' => 'boolean',
        'require_gps' => 'boolean',
        'require_condition_check' => 'boolean',
        'allow_qr_scan' => 'boolean',
        'auto_update_location' => 'boolean',
        'auto_update_status' => 'boolean',
        'auto_assign_missing' => 'boolean',
        'require_review' => 'boolean',
        'create_next_on_complete' => 'boolean',
        'total_assets' => 'integer',
        'completed_assets' => 'integer',
        'found_assets' => 'integer',
        'not_found_assets' => 'integer',
        'mismatched_assets' => 'integer',
        'flagged_assets' => 'integer',
        'completion_rate' => 'decimal:2',
        'accuracy_rate' => 'decimal:2',
        'asset_count' => 'integer',
    ];

    /**
     * 关联负责人
     */
    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * 关联审核人
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * 关联盘点记录
     */
    public function records()
    {
        return $this->hasMany(InventoryRecord::class, 'inventory_task_id');
    }

    /**
     * 任务类型映射
     */
    public static function getTaskTypeLabels()
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
     * 任务状态映射
     */
    public static function getStatusLabels()
    {
        return [
            'draft' => '草稿',
            'active' => '已激活',
            'in_progress' => '进行中',
            'paused' => '已暂停',
            'completed' => '已完成',
            'cancelled' => '已取消',
        ];
    }

    /**
     * 重复类型映射
     */
    public static function getRepeatTypeLabels()
    {
        return [
            'none' => '不重复',
            'daily' => '每天',
            'weekly' => '每周',
            'monthly' => '每月',
            'quarterly' => '每季度',
            'yearly' => '每年',
        ];
    }

    /**
     * 获取任务类型标签
     */
    public function getTaskTypeLabelAttribute()
    {
        return self::getTaskTypeLabels()[$this->task_type] ?? $this->task_type;
    }

    /**
     * 获取任务状态标签
     */
    public function getStatusLabelAttribute()
    {
        return self::getStatusLabels()[$this->status] ?? $this->status;
    }

    /**
     * 获取重复类型标签
     */
    public function getRepeatTypeLabelAttribute()
    {
        return self::getRepeatTypeLabels()[$this->repeat_type] ?? $this->repeat_type;
    }

    /**
     * 生成任务编号
     */
    public static function generateTaskNumber()
    {
        $date = now()->format('Ymd');
        $count = self::whereDate('created_at', today())->count() + 1;
        return 'TASK-' . $date . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    /**
     * 检查任务是否可以开始
     */
    public function canStart()
    {
        return in_array($this->status, ['draft', 'active', 'paused']);
    }

    /**
     * 检查任务是否可以完成
     */
    public function canComplete()
    {
        return $this->status === 'in_progress' && $this->completion_rate >= 100;
    }

    /**
     * 检查任务是否可以取消
     */
    public function canCancel()
    {
        return in_array($this->status, ['draft', 'active', 'in_progress']);
    }

    /**
     * 检查任务是否已过期
     */
    public function isOverdue()
    {
        if ($this->status === 'completed' || $this->status === 'cancelled') {
            return false;
        }
        
        if ($this->end_date && Carbon::parse($this->end_date)->isPast()) {
            return true;
        }
        
        if ($this->scheduled_end && Carbon::parse($this->scheduled_end)->isPast()) {
            return true;
        }
        
        return false;
    }

    /**
     * 检查任务是否今天需要执行
     */
    public function isDueToday()
    {
        if ($this->status === 'completed' || $this->status === 'cancelled') {
            return false;
        }
        
        $today = Carbon::today();
        
        // 检查日期范围
        if ($this->start_date && Carbon::parse($this->start_date)->lte($today) &&
            $this->end_date && Carbon::parse($this->end_date)->gte($today)) {
            return true;
        }
        
        // 检查计划时间
        if ($this->scheduled_start && Carbon::parse($this->scheduled_start)->isSameDay($today)) {
            return true;
        }
        
        return false;
    }

    /**
     * 更新进度信息
     */
    public function updateProgress()
    {
        $total = $this->total_assets;
        $completed = $this->completed_assets;
        
        if ($total > 0) {
            $this->completion_rate = round(($completed / $total) * 100, 2);
        } else {
            $this->completion_rate = 0;
        }
        
        // 更新准确率（找到且匹配的资产比例）
        $matched = $this->found_assets - $this->mismatched_assets;
        if ($this->found_assets > 0) {
            $this->accuracy_rate = round(($matched / $this->found_assets) * 100, 2);
        } else {
            $this->accuracy_rate = null;
        }
        
        $this->save();
    }

    /**
     * 增加完成资产计数
     */
    public function incrementCompletedAsset($record)
    {
        $this->completed_assets++;
        
        if ($record->physical_status === 'found') {
            $this->found_assets++;
        } else {
            $this->not_found_assets++;
        }
        
        if ($record->status_match !== 'matched') {
            $this->mismatched_assets++;
        }
        
        if ($record->hasIssues()) {
            $this->flagged_assets++;
        }
        
        $this->updateProgress();
    }

    /**
     * 获取参与者用户列表
     */
    public function getParticipants()
    {
        if (empty($this->participant_ids)) {
            return collect();
        }
        
        $participantIds = is_array($this->participant_ids) 
            ? $this->participant_ids 
            : json_decode($this->participant_ids, true);
        
        return User::whereIn('id', $participantIds)->get();
    }

    /**
     * 添加参与者
     */
    public function addParticipant($userId)
    {
        $participants = $this->participant_ids ?: [];
        
        if (!is_array($participants)) {
            $participants = json_decode($participants, true) ?: [];
        }
        
        if (!in_array($userId, $participants)) {
            $participants[] = $userId;
            $this->participant_ids = $participants;
        }
    }

    /**
     * 移除参与者
     */
    public function removeParticipant($userId)
    {
        $participants = $this->participant_ids ?: [];
        
        if (!is_array($participants)) {
            $participants = json_decode($participants, true) ?: [];
        }
        
        $participants = array_diff($participants, [$userId]);
        $this->participant_ids = array_values($participants);
    }

    /**
     * 获取部门范围
     */
    public function getDepartments()
    {
        if (empty($this->department_ids)) {
            return collect();
        }
        
        $departmentIds = is_array($this->department_ids)
            ? $this->department_ids
            : json_decode($this->department_ids, true);
        
        return Department::whereIn('id', $departmentIds)->get();
    }

    /**
     * 获取类别范围
     */
    public function getCategories()
    {
        if (empty($this->category_ids)) {
            return collect();
        }
        
        $categoryIds = is_array($this->category_ids)
            ? $this->category_ids
            : json_decode($this->category_ids, true);
        
        return Category::whereIn('id', $categoryIds)->get();
    }

    /**
     * 创建下一个重复任务
     */
    public function createNextRepeatTask()
    {
        if ($this->repeat_type === 'none' || !$this->create_next_on_complete) {
            return null;
        }
        
        $nextTask = $this->replicate();
        $nextTask->task_number = self::generateTaskNumber();
        $nextTask->status = 'draft';
        $nextTask->total_assets = 0;
        $nextTask->completed_assets = 0;
        $nextTask->found_assets = 0;
        $nextTask->not_found_assets = 0;
        $nextTask->mismatched_assets = 0;
        $nextTask->flagged_assets = 0;
        $nextTask->completion_rate = 0;
        $nextTask->accuracy_rate = null;
        $nextTask->started_at = null;
        $nextTask->completed_at = null;
        $nextTask->completion_notes = null;
        
        // 计算下一次的日期
        $startDate = Carbon::parse($this->start_date);
        $endDate = Carbon::parse($this->end_date);
        $duration = $endDate->diffInDays($startDate);
        
        switch ($this->repeat_type) {
            case 'daily':
                $startDate->addDays($this->repeat_interval);
                $endDate->addDays($this->repeat_interval);
                break;
            case 'weekly':
                $startDate->addWeeks($this->repeat_interval);
                $endDate->addWeeks($this->repeat_interval);
                break;
            case 'monthly':
                $startDate->addMonths($this->repeat_interval);
                $endDate->addMonths($this->repeat_interval);
                break;
            case 'quarterly':
                $startDate->addMonths(3 * $this->repeat_interval);
                $endDate->addMonths(3 * $this->repeat_interval);
                break;
            case 'yearly':
                $startDate->addYears($this->repeat_interval);
                $endDate->addYears($this->repeat_interval);
                break;
        }
        
        // 检查是否超过重复截止日期
        if ($this->repeat_until && $startDate->gt(Carbon::parse($this->repeat_until))) {
            return null;
        }
        
        $nextTask->start_date = $startDate->toDateString();
        $nextTask->end_date = $endDate->toDateString();
        
        if ($this->scheduled_start) {
            $nextTask->scheduled_start = Carbon::parse($this->scheduled_start)
                ->addDays($duration * $this->repeat_interval);
        }
        
        if ($this->scheduled_end) {
            $nextTask->scheduled_end = Carbon::parse($this->scheduled_end)
                ->addDays($duration * $this->repeat_interval);
        }
        
        $nextTask->save();
        
        return $nextTask;
    }

    /**
     * 获取任务统计信息
     */
    public function getTaskStatistics()
    {
        $records = $this->records()->get();
        
        $statistics = [
            'total' => $records->count(),
            'found' => $records->where('physical_status', 'found')->count(),
            'not_found' => $records->where('physical_status', 'not_found')->count(),
            'damaged' => $records->where('physical_status', 'damaged')->count(),
            'matched' => $records->where('status_match', 'matched')->count(),
            'location_mismatch' => $records->where('status_match', 'location_mismatch')->count(),
            'user_mismatch' => $records->where('status_match', 'user_mismatch')->count(),
            'both_mismatch' => $records->where('status_match', 'both_mismatch')->count(),
            'condition_good' => $records->where('condition_good', true)->count(),
            'condition_fair' => $records->where('condition_fair', true)->count(),
            'condition_poor' => $records->where('condition_poor', true)->count(),
            'needs_maintenance' => $records->where('needs_maintenance', true)->count(),
            'needs_replacement' => $records->where('needs_replacement', true)->count(),
            'pending_review' => $records->where('review_status', 'pending')->count(),
            'with_issues' => $records->where(function ($record) {
                return $record->hasIssues();
            })->count(),
        ];
        
        // 计算比例
        if ($statistics['total'] > 0) {
            $statistics['found_rate'] = round(($statistics['found'] / $statistics['total']) * 100, 2);
            $statistics['match_rate'] = round(($statistics['matched'] / $statistics['found']) * 100, 2);
            $statistics['issue_rate'] = round(($statistics['with_issues'] / $statistics['total']) * 100, 2);
        } else {
            $statistics['found_rate'] = 0;
            $statistics['match_rate'] = 0;
            $statistics['issue_rate'] = 0;
        }
        
        return $statistics;
    }
}