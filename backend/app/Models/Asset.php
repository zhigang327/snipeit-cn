<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'asset_tag',
        'name',
        'description',
        'category_id',
        'supplier_id',
        'purchase_price',
        'purchase_date',
        'brand',
        'model',
        'serial_number',
        'warranty_expiry',
        'department_id',
        'user_id',
        'location',
        'status',
        'checkout_date',
        'expected_checkin_date',
        'notes',
        'created_by',
        'updated_by',
        'image',
        'warranty_months',
        'qr_code',
        'depreciation_method',
        'salvage_value',
        'useful_life_years',
        'current_book_value',
        'last_depreciation_date',
        'accumulated_depreciation',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'purchase_date' => 'date',
        'checkout_date' => 'date',
        'expected_checkin_date' => 'date',
        'warranty_months' => 'integer',
        'salvage_value' => 'decimal:2',
        'current_book_value' => 'decimal:2',
        'last_depreciation_date' => 'date',
        'accumulated_depreciation' => 'decimal:2',
    ];

    public function category()
    {
        return $this->belongsTo(AssetCategory::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function histories()
    {
        return $this->hasMany(AssetHistory::class);
    }

    public function depreciationRecords()
    {
        return $this->hasMany(DepreciationRecord::class)->orderBy('depreciation_date', 'desc');
    }

    public function maintenanceRecords()
    {
        return $this->hasMany(MaintenanceRecord::class)->orderBy('reported_date', 'desc');
    }

    public function borrowRecords()
    {
        return $this->hasMany(BorrowRecord::class)->orderBy('created_at', 'desc');
    }

    public function currentBorrow()
    {
        return $this->hasOne(BorrowRecord::class)->where('status', 'borrowed')->latest();
    }

    public function scopeAssigned($query)
    {
        return $query->where('status', 'assigned');
    }

    public function scopeReady($query)
    {
        return $query->where('status', 'ready');
    }

    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }
}
