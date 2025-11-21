<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Batch extends Model
{
    use SoftDeletes;

    /**
     * Kolom yang boleh di-mass-assign.
     */
    protected $fillable = [
        'batch_code',
        'heat_number',
        'item_code',
        'item_name',
        'aisi',
        'size',
        'line',
        'cust_name',
        'weight_per_pc',
        'batch_qty',
        'cast_date',
        'import_session_id',
    ];

    /**
     * Casting atribut.
     */
    protected $casts = [
        'cast_date'         => 'date',
        'weight_per_pc'     => 'decimal:3',
        'batch_qty'         => 'integer',
        'import_session_id' => 'integer',
        'deleted_at'        => 'datetime',
    ];

    /**
     * Boot model: auto-generate batch_code saat creating,
     * kecuali sudah diset manual oleh caller.
     */
    protected static function booted(): void
    {
        static::creating(function (Batch $batch) {
            // Jika batch_code sudah diset, jangan di-override
            if (! empty($batch->batch_code)) {
                return;
            }

            // Jika berasal dari import_session: gunakan generator per session
            if (! empty($batch->import_session_id)) {
                $batch->batch_code = static::generateBatchCodeForSession(
                    (int) $batch->import_session_id,
                    $batch->cast_date ? $batch->cast_date->toDateString() : null
                );

                return;
            }

            // Fallback: generate berdasarkan cast_date (atau tanggal hari ini)
            $date = $batch->cast_date
                ? $batch->cast_date->toDateString()
                : Carbon::now()->toDateString();

            $batch->batch_code = static::generateBatchCodeByDate($date);
        });
    }

    /* ===========================
       Helper / Generator Methods
       =========================== */

    /**
     * Generate kode per import_session: PREFIX-YYYYMMDD-SS
     * Menggunakan transaction + lockForUpdate untuk mengurangi race condition.
     */
    public static function generateBatchCodeForSession(
        int $importSessionId,
        ?string $castDate = null,
        string $prefix = 'CR'
    ): string {
        return DB::transaction(function () use ($importSessionId, $castDate, $prefix) {
            // Tanggal: pakai parameter kalau ada, kalau tidak ambil dari import_sessions.date
            if ($castDate) {
                $dateCarbon = Carbon::parse($castDate);
            } else {
                $sessionDate = DB::table('import_sessions')
                    ->where('id', $importSessionId)
                    ->value('date'); // kolom di ImportSession

                $dateCarbon = $sessionDate
                    ? Carbon::parse($sessionDate)
                    : Carbon::now();
            }

            $dateStr = $dateCarbon->format('Ymd');

            // Hitung jumlah batch yang sudah punya batch_code di session ini
            $count = DB::table('batches')
                ->where('import_session_id', $importSessionId)
                ->whereNotNull('batch_code')
                ->lockForUpdate()
                ->count();

            $next = $count + 1;
            $seq  = str_pad((string) $next, 2, '0', STR_PAD_LEFT);

            return sprintf('%s-%s-%s', $prefix, $dateStr, $seq);
        });
    }

    /**
     * Generate kode berdasarkan cast_date: PREFIX-YYYYMMDD-SS
     * Menggunakan transaction + lockForUpdate untuk mengurangi race condition.
     */
    public static function generateBatchCodeByDate(string $date, string $prefix = 'CR'): string
    {
        return DB::transaction(function () use ($date, $prefix) {
            $parsed  = Carbon::parse($date)->toDateString(); // YYYY-MM-DD
            $dateStr = Carbon::parse($parsed)->format('Ymd');

            // Hitung jumlah batch pada tanggal itu yang sudah punya batch_code
            $count = DB::table('batches')
                ->whereDate('cast_date', $parsed)
                ->whereNotNull('batch_code')
                ->lockForUpdate()
                ->count();

            $next = $count + 1;
            $seq  = str_pad((string) $next, 2, '0', STR_PAD_LEFT);

            return sprintf('%s-%s-%s', $prefix, $dateStr, $seq);
        });
    }

    /**
     * Backfill helper: isi batch_code untuk semua batch pada import_session
     * yang masih null. Berguna untuk data lama.
     */
    public static function fillMissingBatchCodesForImportSession(
        int $importSessionId,
        string $prefix = 'CR'
    ): int {
        return DB::transaction(function () use ($importSessionId, $prefix) {
            // Ambil rows yang batch_code null, urut berdasarkan id
            $rows = DB::table('batches')
                ->where('import_session_id', $importSessionId)
                ->whereNull('batch_code')
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id', 'cast_date']);

            $updated = 0;

            foreach ($rows as $row) {
                $castDate = $row->cast_date
                    ? Carbon::parse($row->cast_date)->toDateString()
                    : Carbon::now()->toDateString();

                // Hitung berapa yang sudah punya kode untuk session ini
                $count = DB::table('batches')
                    ->where('import_session_id', $importSessionId)
                    ->whereNotNull('batch_code')
                    ->lockForUpdate()
                    ->count();

                $next    = $count + 1;
                $seq     = str_pad((string) $next, 2, '0', STR_PAD_LEFT);
                $dateStr = Carbon::parse($castDate)->format('Ymd');

                $code = sprintf('%s-%s-%s', $prefix, $dateStr, $seq);

                DB::table('batches')
                    ->where('id', $row->id)
                    ->update([
                        'batch_code' => $code,
                        'updated_at' => now(),
                    ]);

                $updated++;
            }

            return $updated;
        });
    }

    /* ===========================
       Relasi
       =========================== */

    public function importSession()
    {
        return $this->belongsTo(ImportSession::class, 'import_session_id');
    }

    public function defectLines()
    {
        return $this->hasMany(DefectLine::class);
    }
}
