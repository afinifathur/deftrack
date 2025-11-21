<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImportSession extends Model
{
    use SoftDeletes;

    /**
     * Kolom yang boleh di-mass assign.
     */
    protected $fillable = [
        'date',
        'department_id',
        'created_by',
        'note',
    ];

    /**
     * Cast atribut ke tipe yang tepat.
     */
    protected $casts = [
        // date tanpa jam (kalau mau include waktu, ganti jadi 'datetime')
        'date'       => 'date',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relasi ke Department.
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Relasi ke user pembuat session.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relasi ke Batch yang termasuk dalam session ini.
     */
    public function batches()
    {
        return $this->hasMany(Batch::class, 'import_session_id');
    }
}
