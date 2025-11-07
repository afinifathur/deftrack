<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use App\Models\Batch;
use App\Models\Department;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // Range 6 bulan ke belakang s/d akhir pekan ini
        $now  = Carbon::now();
        $from = (clone $now)->subMonths(6)->startOfWeek(); // Senin
        $to   = (clone $now)->endOfWeek();                 // Minggu

        $dateFrom = $from->toDateString();
        $dateTo   = $to->toDateString();

        // ====== Label mingguan + tanggal awal minggu + nomor minggu (ISO)
        $weekCursor   = $from->copy();
        $weekLabels   = []; // "YYYY-Www" -> dipakai sebagai key internal
        $weekStarts   = []; // "YYYY-MM-DD" (Senin)
        $weekNumbers  = []; // "19","20","21",...
        while ($weekCursor->lte($to)) {
            $weekLabels[]  = $weekCursor->format('o-\WW'); // ex: 2025-W20
            $weekStarts[]  = $weekCursor->format('Y-m-d'); // ex: 2025-05-19
            $weekNumbers[] = $weekCursor->format('W');     // ex: "20"
            $weekCursor->addWeek();
        }

        // Helper: konversi YEARWEEK(…,3) -> "YYYY-Www"
        $mapYearWeekToIso = static function ($yw) use ($now) {
            $year = (int) floor($yw / 100);
            $week = (int) ($yw % 100);
            $dt = (clone $now)->setISODate($year, $week)->startOfWeek();
            return $dt->format('o-\WW');
        };

        // ====== PCS & KG per minggu
        $rowsPcs = DB::table('defect_lines as dl')
            ->join('defects as d', 'd.id', '=', 'dl.defect_id')
            ->selectRaw('YEARWEEK(d.date, 3) as yw, SUM(dl.qty_pcs) as pcs')
            ->whereBetween('d.date', [$dateFrom, $dateTo])
            ->groupBy('yw')->orderBy('yw')->get();

        $rowsKg = DB::table('defect_lines as dl')
            ->join('defects as d', 'd.id', '=', 'dl.defect_id')
            ->selectRaw('YEARWEEK(d.date, 3) as yw, SUM(dl.qty_kg) as kg')
            ->whereBetween('d.date', [$dateFrom, $dateTo])
            ->groupBy('yw')->orderBy('yw')->get();

        $pcsMap = [];
        foreach ($rowsPcs as $r) {
            $pcsMap[$mapYearWeekToIso($r->yw)] = (int) $r->pcs;
        }

        $kgMap = [];
        foreach ($rowsKg as $r) {
            $kgMap[$mapYearWeekToIso($r->yw)] = (float) $r->kg;
        }

        $pcsSeries = array_map(fn ($lbl) => $pcsMap[$lbl] ?? 0, $weekLabels);
        $kgSeries  = array_map(fn ($lbl) => $kgMap[$lbl]  ?? 0, $weekLabels);

        // ====== Top Defects (Donut)
        $top = DB::table('defect_lines as dl')
            ->leftJoin('defect_types as dt', 'dt.id', '=', 'dl.defect_type_id')
            ->join('defects as d', 'd.id', '=', 'dl.defect_id')
            ->selectRaw('COALESCE(dt.name,"(Unknown)") as name, SUM(dl.qty_pcs) as pcs')
            ->whereBetween('d.date', [$dateFrom, $dateTo])
            ->groupBy('name')->orderByDesc('pcs')->limit(8)->get();

        $donutLabels = $top->pluck('name');
        $donutData   = $top->pluck('pcs')->map(fn ($v) => (int) $v);

        // ====== NEW: Donut per Departemen (total PCS)
        $deptTotals = DB::table('defect_lines as dl')
            ->join('defects as d', 'd.id', '=', 'dl.defect_id')
            ->join('departments as dep', 'dep.id', '=', 'd.department_id')
            ->selectRaw('dep.name as dept, SUM(dl.qty_pcs) as pcs')
            ->whereBetween('d.date', [$dateFrom, $dateTo])
            ->groupBy('dept')->orderByDesc('pcs')->limit(10)->get();

        $deptDonutLabels = $deptTotals->pluck('dept');
        $deptDonutData   = $deptTotals->pluck('pcs')->map(fn ($v) => (int) $v);

        // ====== Multi-series per department (trend mingguan)
        $deptRows = DB::table('defect_lines as dl')
            ->join('defects as d', 'd.id', '=', 'dl.defect_id')
            ->join('departments as dep', 'dep.id', '=', 'd.department_id')
            ->selectRaw('dep.name as dept, YEARWEEK(d.date, 3) as yw, SUM(dl.qty_pcs) as pcs')
            ->whereBetween('d.date', [$dateFrom, $dateTo])
            ->groupBy('dept', 'yw')->orderBy('dept')->orderBy('yw')->get();

        $deptNames  = Department::orderBy('name')->pluck('name')->all();
        $deptSeries = [];
        foreach ($deptNames as $dept) {
            $filtered = $deptRows->where('dept', $dept);
            $map = [];
            foreach ($filtered as $r) {
                $map[$mapYearWeekToIso($r->yw)] = (int) $r->pcs;
            }
            $deptSeries[] = [
                'label' => $dept,
                'data'  => array_map(fn ($lbl) => $map[$lbl] ?? 0, $weekLabels),
            ];
        }

        // ====== KPI % periode
        $defectPcs = DB::table('defect_lines as dl')
            ->join('defects as d', 'd.id', '=', 'dl.defect_id')
            ->whereBetween('d.date', [$dateFrom, $dateTo])
            ->sum('dl.qty_pcs');

        $batchQty = Batch::whereBetween('cast_date', [$dateFrom, $dateTo])
            ->sum('batch_qty');

        $kpi = $batchQty > 0 ? round(($defectPcs / $batchQty) * 100, 2) : 0;

        // ====== KPI % per minggu (sum defect_pcs / sum batch_qty * 100)
        // defect pcs per week (pakai defects.date)
        $defectPerWeek = DB::table('defect_lines as dl')
            ->join('defects as d', 'd.id', '=', 'dl.defect_id')
            ->selectRaw('YEARWEEK(d.date, 3) as yw, SUM(dl.qty_pcs) as pcs')
            ->whereBetween('d.date', [$dateFrom, $dateTo])
            ->groupBy('yw')->get();

        $defMap = [];
        foreach ($defectPerWeek as $r) {
            $defMap[$mapYearWeekToIso($r->yw)] = (int) $r->pcs;
        }

        // batch qty per week (pakai cast_date)
        $batchPerWeek = DB::table('batches')
            ->selectRaw('YEARWEEK(cast_date, 3) as yw, SUM(batch_qty) as qty')
            ->whereBetween('cast_date', [$dateFrom, $dateTo])
            ->groupBy('yw')->get();

        $batMap = [];
        foreach ($batchPerWeek as $r) {
            $batMap[$mapYearWeekToIso($r->yw)] = (int) $r->qty;
        }

        $kpiWeekly = [];
        foreach ($weekLabels as $lbl) {
            $pcs = $defMap[$lbl] ?? 0;
            $bqt = $batMap[$lbl] ?? 0;
            $kpiWeekly[] = $bqt > 0 ? round(($pcs / $bqt) * 100, 2) : 0;
        }

        // Kirim ke view
        return view('dashboard', compact(
            'kpi',
            'weekLabels',    // untuk pemetaan internal
            'weekStarts',    // untuk plugin label bulan
            'weekNumbers',   // label sumbu-X (nomor minggu)
            'pcsSeries',
            'kgSeries',
            'donutLabels',
            'donutData',
            'deptSeries',
            'kpiWeekly',
            'deptDonutLabels', // NEW
            'deptDonutData'    // NEW
        ));
    }
}
