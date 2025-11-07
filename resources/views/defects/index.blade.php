@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-2">
  <h5 class="mb-0">Defects</h5>
  <a href="{{ route('defects.create') }}" class="btn btn-primary btn-sm">Tambah</a>
</div>

<form class="row g-2 mb-3">
  <div class="col-auto">
    <select name="department_id" class="form-select" onchange="this.form.submit()">
      <option value="">Semua Departemen</option>
      @foreach($departments as $d)
        <option value="{{ $d->id }}" @selected(request('department_id') == $d->id)>{{ $d->name }}</option>
      @endforeach
    </select>
  </div>
</form>

<table class="table table-sm table-bordered">
  <thead>
    <tr>
      <th>Tanggal</th>
      <th>Departemen</th>
      <th>Status</th>
      <th style="width:280px">Aksi</th>
    </tr>
  </thead>
  <tbody>
    @foreach($defects as $df)
      <tr>
        <td>{{ $df->date->format('Y-m-d') }}</td>
        <td>{{ optional($df->department)->name }}</td>
        <td>
          <span class="badge bg-secondary text-uppercase">{{ $df->status }}</span>
        </td>
        <td>
          {{-- Tampilkan tombol berdasar role --}}
          @if($df->status === 'draft' && auth()->user()?->role === 'admin_qc')
            <form method="POST" action="{{ route('defects.submit', $df) }}" class="d-inline">
              @csrf
              <button class="btn btn-sm btn-info">Submit</button>
            </form>
          @endif

          @if($df->status === 'submitted' && auth()->user()?->role === 'kabag_qc')
            <form method="POST" action="{{ route('approvals.approve', $df) }}" class="d-inline">
              @csrf
              <button class="btn btn-sm btn-success">Approve</button>
            </form>
            <form method="POST" action="{{ route('approvals.reject', $df) }}" class="d-inline">
              @csrf
              <input type="hidden" name="reason" value="Tidak sesuai data">
              <button class="btn btn-sm btn-danger">Reject</button>
            </form>
          @endif

          @if($df->status === 'rejected')
            <span class="text-danger">Alasan: {{ $df->rejected_reason }}</span>
          @endif
        </td>
      </tr>
    @endforeach
  </tbody>
</table>

{{ $defects->links() }}
@endsection
