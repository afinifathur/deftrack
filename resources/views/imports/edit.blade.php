@extends('layouts.app')
@section('content')

<h4 class="fw-bold mb-3">✏️ Edit Data Import — {{ $importSession->tanggal }}</h4>

<form method="POST" action="{{ route('imports.update', $importSession->id) }}">
@csrf @method('PUT')

<div class="card shadow-sm">
  <div class="card-body">

    <table class="table table-bordered table-sm">
      <thead class="table-light">
        <tr>
          <th>Heat</th>
          <th>Kode</th>
          <th>Nama Barang</th>
          <th>Berat</th>
          <th>Jumlah</th>
        </tr>
      </thead>
      <tbody>
        @foreach($batches as $b)
        <tr>
          <td><input class="form-control form-control-sm" name="batches[{{ $b->id }}][heat_number]" value="{{ $b->heat_number }}"></td>
          <td><input class="form-control form-control-sm" name="batches[{{ $b->id }}][item_code]" value="{{ $b->item_code }}"></td>
          <td><input class="form-control form-control-sm" name="batches[{{ $b->id }}][item_name]" value="{{ $b->item_name }}"></td>
          <td><input class="form-control form-control-sm" name="batches[{{ $b->id }}][weight_per_pc]" value="{{ $b->weight_per_pc }}"></td>
          <td><input class="form-control form-control-sm" name="batches[{{ $b->id }}][batch_qty]" value="{{ $b->batch_qty }}"></td>
        </tr>
        @endforeach
      </tbody>
    </table>

    <button class="btn btn-primary mt-3">Simpan Perubahan</button>

  </div>
</div>
</form>

@endsection
