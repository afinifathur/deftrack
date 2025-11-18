@extends('layouts.app')
@section('content')

<h4 class="fw-bold mb-3">📄 Detail Import — {{ $importSession->tanggal }}</h4>

<div class="card shadow-sm">
  <div class="card-body">
    <p><strong>Departemen:</strong> {{ $importSession->departemen }}</p>
    <p><strong>Dibuat oleh:</strong> {{ $importSession->pembuat }}</p>

    <table class="table table-bordered table-sm mt-3">
      <thead class="table-light">
        <tr>
          <th>Heat Number</th>
          <th>Kode Barang</th>
          <th>Nama Barang</th>
          <th>Berat / PC</th>
          <th>Batch Qty</th>
        </tr>
      </thead>
      <tbody>
        @foreach($batches as $b)
        <tr>
          <td>{{ $b->heat_number }}</td>
          <td>{{ $b->item_code }}</td>
          <td>{{ $b->item_name }}</td>
          <td>{{ $b->weight_per_pc }}</td>
          <td>{{ $b->batch_qty }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>

    <a href="{{ route('imports.edit', $importSession->id) }}" class="btn btn-warning mt-3">Edit Data</a>
  </div>
</div>

@endsection
