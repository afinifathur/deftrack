<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;

    /**
     * Kolom yang boleh diisi mass assignment.
     */
    protected $fillable = [
        'name',
        'tag',
    ];

    /**
     * Relasi many-to-many ke Department melalui pivot category_department.
     */
    public function departments()
    {
        return $this->belongsToMany(Department::class, 'category_department');
    }

    /**
     * Relasi ke DefectLine.
     * 
     * Sesuaikan foreign key jika di tabel defect_lines nama kolomnya berbeda.
     */
    public function defectLines()
    {
        return $this->hasMany(DefectLine::class, 'defect_type_id');
        // Jika kolomnya `category_id`, gunakan:
        // return $this->hasMany(DefectLine::class, 'category_id');
    }
}
