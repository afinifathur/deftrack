<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Class DefectTypeVariant
 *
 * @package App\Models
 *
 * @property int $id
 * @property int $defect_category_id
 * @property int|null $department_id
 * @property string $label
 * @property string $code
 * @property bool $active
 * @property int $ordering
 */
class DefectTypeVariant extends Model
{
    protected $table = 'defect_type_variants';

    protected $fillable = [
        'defect_category_id',
        'department_id',
        'label',
        'code',
        'active',
        'ordering',
    ];

    /**
     * Relationship: variant -> category
     */
    public function category()
    {
        return $this->belongsTo(DefectCategory::class, 'defect_category_id');
    }

    /**
     * Relationship: variant -> department (nullable for global variants)
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Query scope: restrict variants to a category and optionally a department
     *
     * Usage: DefectTypeVariant::forCategoryAndDepartment($catId, $deptId)->get();
     *
     * @param  Builder  $query
     * @param  int      $categoryId
     * @param  int|null $departmentId
     * @return Builder
     */
    public function scopeForCategoryAndDepartment(Builder $query, int $categoryId, ?int $departmentId = null): Builder
    {
        return $query
            ->where('defect_category_id', $categoryId)
            ->where(function (Builder $q) use ($departmentId) {
                $q->where('department_id', $departmentId)
                  ->orWhereNull('department_id');
            })
            ->orderByRaw('department_id IS NULL, ordering ASC');
    }

    /**
     * Helper: pick single best variant for category + (optional) department.
     * Prefers department-specific variant; falls back to global (department_id IS NULL).
     *
     * @param  int      $categoryId
     * @param  int|null $departmentId
     * @return self|null
     */
    public static function pickForCategoryAndDepartment(int $categoryId, ?int $departmentId = null): ?self
    {
        return self::forCategoryAndDepartment($categoryId, $departmentId)->first();
    }
}
