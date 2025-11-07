<?php

namespace App\Http\Controllers;

use App\Exports\DefectsExport;
use App\Models\Department;
use App\Models\DefectLine;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index()
    {
        $departments = Department::where('is_active', 1)->orderBy('name')->get();

        return view('reports.index', compact('departments'));
    }

    /**
     * Validasi + guard rentang tanggal.
     * @return array{from: Carbon, to: Carbon}
     */
    protected function validateAndGuardDateRange(Request $request, int $maxDays): array
    {
        // Validasi dasar
        Validator::make($request->all(), [
            'from' => ['required', 'date'],
            'to'   => ['required', 'date', 'after_or_equal:from'],
            'department_id' => ['nullable', 'integer'],
            'type_name'     => ['nullable', 'string'],
        ], [], [
            'from' => 'tanggal awal',
            'to'   => 'tanggal akhir',
        ])->validate();

        $from = Carbon::parse($request->input('from'))->startOfDay();
        $to   = Carbon::parse($request->input('to'))->endOfDay();

        if ($from->diffInDays($to) > $maxDays) {
            abort(Response::HTTP_UNPROCESSABLE_ENTITY, "Rentang waktu maksimal {$maxDays} hari.");
        }

        return compact('from', 'to');
    }

    /**
     * Query dasar dengan eager load & filter dinamis.
     */
    protected function baseQuery(Request $request, Carbon $from, Carbon $to)
    {
        $q = DefectLine::query()
            ->with([
                'defect:id,date,department_id',
                'defect.department:id,name',
                'batch:id,heat_number,item_code',
                'type:id,name',
            ])
            ->whereHas('defect', function ($qq) use ($request, $from, $to) {
                $qq->whereBetween('date', [$from->toDateString(), $to->toDateString()])
                   ->when($request->filled('department_id'), function ($q2) use ($request) {
                       $q2->where('department_id', $request->integer('department_id'));
                   });
            })
            ->when($request->filled('type_name'), function ($qq) use ($request) {
                $name = $request->string('type_name')->toString();
                $qq->whereHas('type', fn ($t) => $t->where('name', 'like', "%{$name}%"));
            })
            // penting untuk chunkById
            ->orderBy('id');

        return $q;
    }

    public function exportCsv(Request $request)
    {
        // Guard 3 bulan ≈ 95 hari
        ['from' => $from, 'to' => $to] = $this->validateAndGuardDateRange($request, 95);

        $filename = 'deftrack_export_' . now('Asia/Jakarta')->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($request, $from, $to) {
            $out = fopen('php://output', 'w');

            // Header
            fputcsv($out, ['date', 'department', 'heat_number', 'item_code', 'defect_type', 'qty_pcs', 'qty_kg']);

            // Stream data
            $this->baseQuery($request, $from, $to)->chunkById(1000, function ($chunk) use ($out) {
                foreach ($chunk as $l) {
                    fputcsv($out, [
                        optional($l->defect)->date,
                        optional(optional($l->defect)->department)->name,
                        optional($l->batch)->heat_number,
                        optional($l->batch)->item_code,
                        optional($l->type)->name,
                        $l->qty_pcs,
                        $l->qty_kg,
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportXlsx(Request $request)
    {
        // Guard 3 bulan ≈ 95 hari
        ['from' => $from, 'to' => $to] = $this->validateAndGuardDateRange($request, 95);

        // Kumpulkan rows (tetap chunk supaya hemat memori)
        $rows = collect();
        $this->baseQuery($request, $from, $to)->chunkById(2000, function ($chunk) use (&$rows) {
            foreach ($chunk as $l) {
                $rows->push((object) [
                    'date'        => optional($l->defect)->date,
                    'department'  => optional(optional($l->defect)->department)->name,
                    'heat_number' => optional($l->batch)->heat_number,
                    'item_code'   => optional($l->batch)->item_code,
                    'defect_type' => optional($l->type)->name,
                    'qty_pcs'     => $l->qty_pcs,
                    'qty_kg'      => $l->qty_kg,
                ]);
            }
        });

        $filename = 'deftrack_export_' . now('Asia/Jakarta')->format('Ymd_His') . '.xlsx';

        return Excel::download(new DefectsExport($rows), $filename);
    }

    public function exportPdf(Request $request)
    {
        // Guard 1 bulan ≈ 31 hari
        ['from' => $from, 'to' => $to] = $this->validateAndGuardDateRange($request, 31);

        // Ambil data untuk view
        $rows = $this->baseQuery($request, $from, $to)->get()->map(function ($l) {
            return (object) [
                'date'        => optional($l->defect)->date,
                'department'  => optional(optional($l->defect)->department)->name,
                'heat_number' => optional($l->batch)->heat_number,
                'item_code'   => optional($l->batch)->item_code,
                'defect_type' => optional($l->type)->name,
                'qty_pcs'     => $l->qty_pcs,
                'qty_kg'      => $l->qty_kg,
            ];
        });

        $meta = [
            'from'         => $from->toDateString(),
            'to'           => $to->toDateString(),
            'generated_at' => now('Asia/Jakarta')->format('Y-m-d H:i'),
        ];

        $pdf = Pdf::loadView('reports.pdf', compact('rows', 'meta'))
            ->setPaper('a4', 'portrait');

        $filename = 'deftrack_report_' . $meta['from'] . '_' . $meta['to'] . '.pdf';

        return $pdf->download($filename);
    }

    public function estimate(Request $request)
    {
        // Guard 3 bulan untuk estimasi juga, biar konsisten
        ['from' => $from, 'to' => $to] = $this->validateAndGuardDateRange($request, 95);

        $count = $this->baseQuery($request, $from, $to)->count();

        return response()->json(['count' => $count]);
    }
}
