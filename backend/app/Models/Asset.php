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
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'purchase_date' => 'date',
        'checkout_date' => 'date',
        'expected_checkin_date' => 'date',
        'warranty_months' => 'integer',
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
