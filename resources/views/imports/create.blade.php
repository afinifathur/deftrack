@extends('layouts.app')
@section('content')
<h5>Import Master Batch (CSV)</h5>
<form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" class="mt-3">@csrf
<div class="row g-2">
<div class="col-md-3"><label class="form-label">Tanggal</label><input type="date" name="date" class="form-control" required></div>
<div class="col-md-3"><label class="form-label">Departemen</label><select name="department_id" class="form-select" required>
@foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
</select></div>
<div class="col-md-6"><label class="form-label">File</label><input type="file" name="file" class="form-control" accept=".csv,.txt,.xlsx,.xls" required>
<small class="text-muted">Kolom: heat_number, item_code, item_name, weight_per_pc, batch_qty</small></div>
</div>
<div class="mt-2"><label class="form-label">Catatan (opsional)</label><input type="text" name="note" class="form-control"></div>
<button class="btn btn-primary mt-3">Upload</button>
</form>
@endsection
