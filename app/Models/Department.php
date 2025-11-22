<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    /**
     * Atribut yang boleh diisi mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'is_active',
    ];

    /**
     * Casting atribut ke tipe tertentu.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Relasi many-to-many ke Category.
     *
     * Pivot: category_department
     *  - department_id
     *  - category_id
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function categories()
    {
        return $this->belongsToMany(
            Category::class,        // model terkait
            'category_department',  // nama tabel pivot
            'department_id',        // FK ke tabel departments
            'category_id'           // FK ke tabel categories
        );
    }
}
