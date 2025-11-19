<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImportSession extends Model
{
    protected $fillable = [
        'date', 'department_id', 'created_by', 'note', // ...
    ];

    // cast date to Carbon (datetime)
    protected $casts = [
        'date' => 'datetime', // sekarang akan memiliki waktu
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function batches()
    {
        return $this->hasMany(Batch::class, 'import_session_id');
    }
}
