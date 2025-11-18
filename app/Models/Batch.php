<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class Batch extends Model
{
    use SoftDeletes;

    // kolom yang boleh di-mass-assign
    protected $fillable = [
        'heat_number',
        'item_code',
        'item_name',
        'weight_per_pc',
        'batch_qty',
        'cast_date',
        'import_session_id',
        'batch_code',
    ];

    // casting tanggal
    protected $casts = [
        'cast_date' => 'date',
        'weight_per_pc' => 'decimal:3',
        'batch_qty' => 'integer',
        'import_session_id' => 'integer',
    ];

    /**
     * Boot model untuk generate batch_code saat creating.
     */
    protected static function booted()
    {
        static::creating(function (Batch $batch) {
            if (empty($batch->batch_code)) {
                // pakai import_session_id jika tersedia (lebih deterministik saat import)
                if (!empty($batch->import_session_id)) {
                    $batch->batch_code = self::generateBatchCodeForImportSession($batch->import_session_id);
                } else {
                    // fallback: generate berdasarkan cast_date
                    $date = $batch->cast_date ? $batch->cast_date->toDateString() : now()->toDateString();
                    $batch->batch_code = self::generateBatchCodeByDate($date);
                }
            }
        });
    }

    /**
     * Generate kode per import_session: CR-YYYYMMDD-SS where SS = sequence within that import session.
     * Lebih aman untuk import massal karena sequence terbatas pada session yang sama.
     */
    public static function generateBatchCodeForImportSession(int $importSessionId): string
    {
        // ambil cast_date dari import session jika tersedia (opsional) supaya kode juga mengandung tanggal.
        $sessionDate = DB::table('import_sessions')
            ->where('id', $importSessionId)
            ->value('session_date'); // asumsi ada kolom session_date, jika tidak ada, pakai today

        $date = $sessionDate ? Carbon::parse($sessionDate)->format('Ymd') : Carbon::now()->format('Ymd');

        // hitung existing batches dalam import_session (tidak menghitung soft deleted karena default)
        $count = self::where('import_session_id', $importSessionId)->count();
        $index = $count + 1;
        $idx = str_pad($index, 2, '0', STR_PAD_LEFT);

        return "CR-{$date}-{$idx}";
    }

    /**
     * Generate kode berdasarkan cast_date: CR-YYYYMMDD-SS where SS = sequence for that date.
     * Perhatikan: query ini menghitung rows saat ini sehingga mungkin ada race condition jika banyak proses create paralel.
     */
    public static function generateBatchCodeByDate(string $date): string
    {
        // pastikan $date dalam format YYYY-MM-DD
        $parsed = Carbon::parse($date)->toDateString();
        $d = Carbon::parse($parsed)->format('Ymd');

        // hitung jumlah batch pada tanggal itu (mengabaikan soft-deleted)
        $count = self::whereDate('cast_date', $parsed)->count();
        $index = $count + 1;
        $idx = str_pad($index, 2, '0', STR_PAD_LEFT);

        return "CR-{$d}-{$idx}";
    }

    /* Relasi */
    public function importSession()
    {
        return $this->belongsTo(ImportSession::class);
    }

    public function defectLines()
    {
        return $this->hasMany(DefectLine::class);
    }
}
