<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class MaintenanceRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_id',
        'reported_by',
        'assigned_to',
        'title',
        'description',
        'diagnosis',
        'solution',
        'status',
        'priority',
        'type',
        'reported_date',
        'start_date',
        'completed_date',
        'estimated_hours',
        'actual_hours',
        'estimated_cost',
        'actual_cost',
        'vendor',
        'vendor_contact',
        'parts_used',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'reported_date' => 'date',
        'start_date' => 'date',
        'completed_date' => 'date',
        'estimated_hours' => 'integer',
        'actual_hours' => 'integer',
        'estimated_cost' => 'decimal:2',
        'actual_cost' => 'decimal:2',
    ];

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignedTo()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByAsset($query, $assetId)
    {
        return $query->where('asset_id', $assetId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByReporter($query, $reporterId)
    {
        return $query->where('reported_by', $reporterId);
    }

    public function scopeByAssignee($query, $assigneeId)
    {
        return $query->where('assigned_to', $assigneeId);
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->orWhere('status', 'in_progress')
            ->whereDate('reported_date', '<', now()->subDays(7));
    }

    /**
     * 计算维修耗时（天）
     */
    public function getDurationDaysAttribute()
    {
        if (!$this->completed_date || !$this->reported_date) {
            return null;
        }

        return $this->reported_date->diffInDays($this->completed_date);
    }

    /**
     * 检查是否逾期
     */
    public function getIsOverdueAttribute()
    {
        if (in_array($this->status, ['completed', 'cancelled'])) {
            return false;
        }

        return $this->reported_date->diffInDays(now()) > 7;
    }

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute()
    {
        $statusMap = [
            'pending' => '待处理',
            'in_progress' => '处理中',
            'completed' => '已完成',
            'cancelled' => '已取消',
        ];

        return $statusMap[$this->status] ?? $this->status;
    }

    /**
     * 获取优先级文本
     */
    public function getPriorityTextAttribute()
    {
        $priorityMap = [
            'low' => '低',
            'medium' => '中',
            'high' => '高',
            'urgent' => '紧急',
        ];

        return $priorityMap[$this->priority] ?? $this->priority;
    }

    /**
     * 获取类型文本
     */
    public function getTypeTextAttribute()
    {
        $typeMap = [
            'hardware' => '硬件',
            'software' => '软件',
            'network' => '网络',
            'other' => '其他',
        ];

        return $typeMap[$this->type] ?? $this->type;
    }

    /**
     * 获取状态颜色
     */
    public function getStatusColorAttribute()
    {
        return [
            'pending' => 'warning',
            'in_progress' => 'info',
            'completed' => 'success',
            'cancelled' => 'danger',
        ][$this->status] ?? 'default';
    }

    /**
     * 获取优先级颜色
     */
    public function getPriorityColorAttribute()
    {
        return [
            'low' => 'success',
            'medium' => 'info',
            'high' => 'warning',
            'urgent' => 'danger',
        ][$this->priority] ?? 'default';
    }

    /**
     * 更新资产状态为维修中
     */
    public function markAssetAsMaintenance()
    {
        $this->asset->update(['status' => 'maintenance']);
    }

    /**
     * 更新资产状态为在库（维修完成）
     */
    public function markAssetAsReady()
    {
        if ($this->status === 'completed') {
            $this->asset->update(['status' => 'ready']);
        }
    }

    /**
     * 更新资产状态为已损坏
     */
    public function markAssetAsBroken()
    {
        $this->asset->update(['status' => 'broken']);
    }
}