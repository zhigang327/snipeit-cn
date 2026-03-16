<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'parent_id',
        'manager_id',
        'sort',
        'location',
        'phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort' => 'integer',
    ];

    // 父部门关系
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    // 子部门关系
    public function children(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    // 递归获取所有子孙部门
    public function descendants()
    {
        return $this->children()->with('descendants');
    }

    // 递归获取所有祖先部门
    public function ancestors()
    {
        return $this->parent ? $this->parent->ancestors()->get()->merge([$this->parent]) : collect();
    }

    // 获取部门层级路径
    public function getPathAttribute(): string
    {
        $path = collect([$this->name]);
        $parent = $this->parent;

        while ($parent) {
            $path->prepend($parent->name);
            $parent = $parent->parent;
        }

        return $path->join(' > ');
    }

    // 获取完整层级路径ID
    public function getPathIdsAttribute(): array
    {
        $ids = collect([$this->id]);
        $parent = $this->parent;

        while ($parent) {
            $ids->prepend($parent->id);
            $parent = $parent->parent;
        }

        return $ids->toArray();
    }

    // 获取层级深度
    public function getDepthAttribute(): int
    {
        $depth = 0;
        $parent = $this->parent;

        while ($parent) {
            $depth++;
            $parent = $parent->parent;
        }

        return $depth;
    }

    // 部门负责人
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    // 部门下的所有用户
    public function users()
    {
        return $this->hasMany(User::class);
    }

    // 部门下的所有资产
    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    // 部门及其所有子部门的用户
    public function allUsers()
    {
        $departmentIds = $this->getAllDescendantIds();
        $departmentIds[] = $this->id;

        return User::whereIn('department_id', $departmentIds);
    }

    // 获取所有子孙部门ID
    public function getAllDescendantIds(): array
    {
        $ids = [];

        foreach ($this->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $child->getAllDescendantIds());
        }

        return $ids;
    }

    // 统计所有子孙部门的资产数量
    public function getAllAssetsCount(): int
    {
        $departmentIds = $this->getAllDescendantIds();
        $departmentIds[] = $this->id;

        return Asset::whereIn('department_id', $departmentIds)->count();
    }

    // 统计所有子孙部门的总资产价值
    public function getTotalAssetValue(): float
    {
        $departmentIds = $this->getAllDescendantIds();
        $departmentIds[] = $this->id;

        return Asset::whereIn('department_id', $departmentIds)->sum('purchase_price');
    }

    // 查询根部门
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    // 查询激活的部门
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // 获取部门树形结构
    public static function getTree()
    {
        return self::active()
            ->with('children')
            ->orderBy('sort')
            ->orderBy('id')
            ->whereNull('parent_id')
            ->get();
    }

    // 获取所有祖先部门ID数组（用于循环引用检测）
    public function getAncestorIdsAttribute(): array
    {
        $ids = [];
        $parent = $this->parent;
        while ($parent) {
            $ids[] = $parent->id;
            $parent = $parent->parent;
        }
        return $ids;
    }

    // 检查是否是某个部门的子孙
    public function isDescendantOf($departmentId): bool
    {
        return in_array($departmentId, $this->ancestor_ids);
    }

    // 检查是否是某个部门的祖先
    public function isAncestorOf($departmentId): bool
    {
        $descendantIds = $this->getAllDescendantIds();
        return in_array($departmentId, $descendantIds);
    }
}
