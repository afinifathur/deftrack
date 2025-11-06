@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-2">
  <h5>Import Sessions</h5><a href="{{ route('imports.create') }}" class="btn btn-primary btn-sm">Import Baru</a>
</div>
<table class="table table-sm table-bordered"><thead><tr><th>Tanggal</th><th>Departemen</th><th>Batches</th><th>Aksi</th></tr></thead><tbody>
@foreach($sessions as $s)
<tr><td>{{ $s->date->format('Y-m-d') }}</td><td>{{ optional($s->department)->name }}</td><td>{{ $s->batches()->count() }}</td>
<td><form method="POST" action="{{ route('imports.destroy',$s) }}">@csrf @method('DELETE')<button class="btn btn-danger btn-sm">Hapus</button></form></td></tr>
@endforeach
</tbody></table>
{{ $sessions->links() }}
@endsection
