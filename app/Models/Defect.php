<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Defect extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'date',
        'department_id',
        'status',
        'submitted_by',
        'approved_by',
        'rejected_reason',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    /**
     * Relasi ke Department.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Relasi hasMany standar (preferred name).
     */
    public function lines(): HasMany
    {
        return $this->hasMany(DefectLine::class);
    }

    /**
     * Alias relasi untuk kompatibilitas kode lama/view/controller
     * (beberapa tempat di project Anda memanggil ->defect_lines).
     */
    public function defect_lines(): HasMany
    {
        return $this->lines();
    }
}
