<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BorrowRecord extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'asset_id',
        'borrower_id',
        'approver_id',
        'borrow_purpose',
        'borrow_date',
        'expected_return_date',
        'actual_return_date',
        'deposit_amount',
        'deposit_returned',
        'status',
        'borrow_conditions',
        'rejection_reason',
        'return_notes',
        'damage_description',
        'damage_fee',
        'damage_resolved',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'borrow_date' => 'date',
        'expected_return_date' => 'date',
        'actual_return_date' => 'date',
        'deposit_amount' => 'decimal:2',
        'deposit_returned' => 'boolean',
        'damage_fee' => 'decimal:2',
        'damage_resolved' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 状态映射
     */
    public const STATUS = [
        'pending' => '待审批',
        'approved' => '已批准',
        'rejected' => '已拒绝',
        'borrowed' => '已借出',
        'returned' => '已归还',
        'overdue' => '逾期未还',
        'cancelled' => '已取消',
    ];

    /**
     * 获取状态文本
     */
    public function getStatusTextAttribute(): string
    {
        return self::STATUS[$this->status] ?? $this->status;
    }

    /**
     * 获取状态颜色
     */
    public function getStatusColorAttribute(): string
    {
        $colors = [
            'pending' => 'warning',
            'approved' => 'info',
            'rejected' => 'danger',
            'borrowed' => 'primary',
            'returned' => 'success',
            'overdue' => 'error',
            'cancelled' => 'default',
        ];

        return $colors[$this->status] ?? 'default';
    }

    /**
     * 是否已逾期
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'borrowed' && 
               $this->expected_return_date < now()->toDateString() &&
               empty($this->actual_return_date);
    }

    /**
     * 计算逾期天数
     */
    public function getOverdueDaysAttribute(): int
    {
        if (!$this->is_overdue) {
            return 0;
        }

        $expected = \Carbon\Carbon::parse($this->expected_return_date);
        $today = \Carbon\Carbon::today();
        
        return $expected->diffInDays($today);
    }

    /**
     * 获取借用时长（天数）
     */
    public function getBorrowDurationAttribute(): int
    {
        if (empty($this->actual_return_date)) {
            $end = \Carbon\Carbon::today();
        } else {
            $end = \Carbon\Carbon::parse($this->actual_return_date);
        }

        $start = \Carbon\Carbon::parse($this->borrow_date);
        return $start->diffInDays($end);
    }

    /**
     * 资产关系
     */
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * 借用人关系
     */
    public function borrower()
    {
        return $this->belongsTo(User::class, 'borrower_id');
    }

    /**
     * 审批人关系
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /**
     * 借用记录
     */
    public function scopeBorrowed($query)
    {
        return $query->where('status', 'borrowed');
    }

    /**
     * 逾期记录
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', 'borrowed')
                    ->whereDate('expected_return_date', '<', now()->toDateString());
    }

    /**
     * 待审批记录
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * 按借用人筛选
     */
    public function scopeByBorrower($query, $borrowerId)
    {
        return $query->where('borrower_id', $borrowerId);
    }

    /**
     * 按资产筛选
     */
    public function scopeByAsset($query, $assetId)
    {
        return $query->where('asset_id', $assetId);
    }

    /**
     * 获取借用历史
     */
    public function scopeHistory($query, $assetId = null, $borrowerId = null)
    {
        if ($assetId) {
            $query->where('asset_id', $assetId);
        }
        
        if ($borrowerId) {
            $query->where('borrower_id', $borrowerId);
        }
        
        return $query->whereIn('status', ['returned', 'cancelled', 'rejected'])
                    ->orderBy('created_at', 'desc');
    }

    /**
     * 获取当前借用
     */
    public function scopeCurrentBorrows($query, $assetId = null, $borrowerId = null)
    {
        if ($assetId) {
            $query->where('asset_id', $assetId);
        }
        
        if ($borrowerId) {
            $query->where('borrower_id', $borrowerId);
        }
        
        return $query->where('status', 'borrowed')
                    ->orderBy('expected_return_date', 'asc');
    }
}