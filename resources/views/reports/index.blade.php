@extends('layouts.app')
@section('content')
<h5>Laporan & Export</h5>
<form class="row g-2 mb-3" method="GET" action="{{ route('reports.exportCsv') }}">
<div class="col-auto"><label class="form-label">Dari</label><input type="date" name="from" class="form-control" required></div>
<div class="col-auto"><label class="form-label">Sampai</label><input type="date" name="to" class="form-control" required></div>
<div class="col-auto align-self-end"><button class="btn btn-success">Export CSV</button></div>
</form>
<p class="text-muted">PDF (≤1 bulan) & XLSX (≤3 bulan) dapat ditambahkan via dompdf & maatwebsite/excel.</p>
@endsection
