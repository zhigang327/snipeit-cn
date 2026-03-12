<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class DisposalRecord extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_id',
        'user_id',
        'disposal_number',
        'disposal_type',
        'disposal_date',
        'disposal_amount',
        'salvage_value',
        'book_value',
        'gain_loss',
        'reason',
        'description',
        'recipient_name',
        'recipient_contact',
        'document_number',
        'approval_number',
        'approved_by',
        'approved_at',
        'status',
        'rejection_reason',
        'final_location',
        'handover_notes',
        'environmental_impact',
    ];

    protected $casts = [
        'disposal_date' => 'date',
        'disposal_amount' => 'decimal:2',
        'salvage_value' => 'decimal:2',
        'book_value' => 'decimal:2',
        'gain_loss' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    /**
     * 关联资产
     */
    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * 关联申请人
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 关联审批人
     */
    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * 报废类型映射
     */
    public static function getDisposalTypeLabels()
    {
        return [
            'sold' => '出售',
            'scrapped' => '报废',
            'donated' => '捐赠',
            'transferred' => '调拨',
            'lost' => '丢失',
        ];
    }

    /**
     * 状态映射
     */
    public static function getStatusLabels()
    {
        return [
            'pending' => '待审批',
            'approved' => '已批准',
            'rejected' => '已拒绝',
            'completed' => '已完成',
        ];
    }

    /**
     * 获取报废类型标签
     */
    public function getDisposalTypeLabelAttribute()
    {
        return self::getDisposalTypeLabels()[$this->disposal_type] ?? $this->disposal_type;
    }

    /**
     * 获取状态标签
     */
    public function getStatusLabelAttribute()
    {
        return self::getStatusLabels()[$this->status] ?? $this->status;
    }

    /**
     * 检查是否可以审批
     */
    public function canApprove()
    {
        return $this->status === 'pending';
    }

    /**
     * 检查是否可以完成
     */
    public function canComplete()
    {
        return $this->status === 'approved';
    }

    /**
     * 生成报废编号
     */
    public static function generateDisposalNumber()
    {
        $date = now()->format('Ymd');
        $count = self::whereDate('created_at', today())->count() + 1;
        return 'DIS-' . $date . '-' . str_pad($count, 3, '0', STR_PAD_LEFT);
    }

    /**
     * 计算处置损益
     */
    public function calculateGainLoss()
    {
        if ($this->disposal_amount && $this->book_value) {
            return $this->disposal_amount - $this->book_value;
        }
        return 0;
    }

    /**
     * 获取损益类型（盈利/亏损）
     */
    public function getGainLossTypeAttribute()
    {
        if ($this->gain_loss > 0) {
            return 'gain';
        } elseif ($this->gain_loss < 0) {
            return 'loss';
        }
        return 'neutral';
    }

    /**
     * 获取损益类型标签
     */
    public function getGainLossTypeLabelAttribute()
    {
        return [
            'gain' => '盈利',
            'loss' => '亏损',
            'neutral' => '持平',
        ][$this->gain_loss_type] ?? '未知';
    }

    /**
     * 范围查询：待审批的报废记录
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * 范围查询：已批准的报废记录
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * 范围查询：已完成的报废记录
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * 范围查询：按日期范围
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('disposal_date', [$startDate, $endDate]);
    }

    /**
     * 范围查询：按报废类型
     */
    public function scopeByType($query, $type)
    {
        return $query->where('disposal_type', $type);
    }

    /**
     * 根据状态获取统计信息
     */
    public static function getStatistics()
    {
        return [
            'total' => self::count(),
            'pending' => self::pending()->count(),
            'approved' => self::approved()->count(),
            'completed' => self::completed()->count(),
            'rejected' => self::where('status', 'rejected')->count(),
        ];
    }

    /**
     * 获取月度报废统计
     */
    public static function getMonthlyStats($year = null)
    {
        $year = $year ?? now()->year;
        
        return self::whereYear('disposal_date', $year)
            ->selectRaw('MONTH(disposal_date) as month')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(disposal_amount) as total_amount')
            ->selectRaw('SUM(book_value) as total_book_value')
            ->groupBy('month')
            ->orderBy('month')
            ->get();
    }
}