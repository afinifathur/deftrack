<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    /**
     * Kolom yang boleh diisi melalui mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'tag',
    ];

    /**
     * Relasi many-to-many ke Department melalui pivot category_department.
     *
     * Pivot: category_department
     *  - category_id
     *  - department_id
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function departments()
    {
        return $this->belongsToMany(
            Department::class,      // model terkait
            'category_department',  // nama tabel pivot
            'category_id',          // FK ke tabel categories
            'department_id'         // FK ke tabel departments
        );
    }

    /**
     * Relasi one-to-many ke DefectLine.
     *
     * Sesuaikan foreign key dengan struktur tabel `defect_lines`.
     * Contoh saat ini menggunakan kolom `defect_type_id`.
     *
     * Jika sebenarnya kolomnya `category_id`, ganti menjadi:
     *   return $this->hasMany(DefectLine::class, 'category_id');
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function defectLines()
    {
        return $this->hasMany(DefectLine::class, 'defect_type_id');
    }
}
