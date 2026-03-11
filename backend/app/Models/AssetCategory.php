<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

class AssetCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'parent_id',
        'image',
        'sort',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    public function parent()
    {
        return $this->belongsTo(AssetCategory::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(AssetCategory::class, 'parent_id');
    }

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function getTree()
    {
        return self::active()
            ->with('children')
            ->orderBy('sort')
            ->orderBy('id')
            ->whereNull('parent_id')
            ->get();
    }
}
