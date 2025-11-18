@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4>Defect #{{ $defect->id }} — {{ optional($defect->department)->name }}</h4>
  <div>
    @if(in_array(auth()->user()?->role, ['admin_qc','kabag_qc','direktur','mr']))
      <a href="{{ route('defects.edit', $defect->id) }}" class="btn btn-sm btn-warning">Edit</a>
    @endif
    @if(in_array(auth()->user()?->role, ['kabag_qc','direktur','mr']))
      <form action="{{ route('defects.destroy',$defect->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Pindahkan ke recycle?')">
        @csrf @method('DELETE')
        <button class="btn btn-sm btn-danger">Delete</button>
      </form>
    @endif
  </div>
</div>

<div class="card mb-3">
  <div class="card-body">
    <p><strong>Tanggal:</strong> {{ $defect->date }}</p>
    <p><strong>Status:</strong> {{ strtoupper($defect->status) }}</p>
    <p><strong>Catatan:</strong> {{ $defect->notes ?? '-' }}</p>
    <hr/>
    <h6>Detail Lines</h6>
    <table class="table table-sm">
      <thead class="table-light">
        <tr><th>Heat</th><th>Item</th><th>Kategori</th><th>Sub</th><th>PCS</th><th>KG</th></tr>
      </thead>
      <tbody>
        @foreach($defect->lines as $line)
        <tr>
          <td>{{ optional($line->batch)->heat_number }}</td>
          <td>{{ optional($line->batch)->item_code }}</td>
          <td>{{ optional($line->type)->name }}</td>
          <td>{{ optional($line->subtype)->name }}</td>
          <td>{{ $line->qty_pcs }}</td>
          <td>{{ $line->qty_kg }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

@endsection
