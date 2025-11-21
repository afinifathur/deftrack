@extends('layouts.app')
@section('content')
<h3>Kategori Kerusakan</h3>
<form method="POST" action="{{ route('settings.categories.store') }}">@csrf
<div class="row g-2">
<div class="col-md-4"><input name="name" class="form-control" placeholder="Nama kategori"></div>
<div class="col-md-4">
<select name="departments[]" class="form-select" multiple>
@foreach($departments as $d)
<option value="{{ $d->id }}">{{ $d->name }}</option>
@endforeach
</select>
</div>
<div class="col-md-2"><button class="btn btn-primary">Tambah</button></div>
</div>
</form>


<table class="table mt-3">
<thead><tr><th>Nama</th><th>Dipakai di Dept</th><th>Aksi</th></tr></thead>
<tbody>
@foreach($categories as $cat)
<tr>
<td>{{ $cat->name }}</td>
<td>{{ $cat->departments->pluck('name')->join(', ') }}</td>
<td>
<!-- simple edit form modal can be added; for brevity use link to edit -->
</td>
</tr>
@endforeach
</tbody>
</table>
@endsection