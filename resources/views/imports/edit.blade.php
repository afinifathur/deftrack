@extends('layouts.app')
@section('content')

<h4 class="fw-bold mb-3">✏️ Edit Data Import — {{ $importSession->tanggal }}</h4>

<form method="POST" action="{{ route('imports.update', $importSession->id) }}">
  @csrf
  @method('PUT')

  <div class="card shadow-sm">
    <div class="card-body">

      <table class="table table-bordered table-sm">
        <thead class="table-light">
          <tr>
            <th>Heat</th>
            <th>Kode</th>
            <th>Nama Barang</th>
            <th>AISI</th>
            <th>Size</th>
            <th>Line</th>
            <th>Cust</th>
            <th>Berat</th>
            <th>Jumlah</th>
          </tr>
        </thead>
        <tbody>
          @foreach($batches as $b)
          <tr>
            <td>
              <input class="form-control form-control-sm" 
                     name="batches[{{ $b->id }}][heat_number]" 
                     value="{{ old('batches.'.$b->id.'.heat_number', $b->heat_number) }}">
            </td>

            <td>
              <input class="form-control form-control-sm" 
                     name="batches[{{ $b->id }}][item_code]" 
                     value="{{ old('batches.'.$b->id.'.item_code', $b->item_code) }}">
            </td>

            <td>
              <input class="form-control form-control-sm" 
                     name="batches[{{ $b->id }}][item_name]" 
                     value="{{ old('batches.'.$b->id.'.item_name', $b->item_name) }}">
            </td>

            <td>
              <input class="form-control form-control-sm" 
                     name="batches[{{ $b->id }}][aisi]" 
                     value="{{ old('batches.'.$b->id.'.aisi', $b->aisi) }}">
            </td>

            <td>
              <input class="form-control form-control-sm" 
                     name="batches[{{ $b->id }}][size]" 
                     value="{{ old('batches.'.$b->id.'.size', $b->size) }}">
            </td>

            <td>
              <input class="form-control form-control-sm" 
                     name="batches[{{ $b->id }}][line]" 
                     value="{{ old('batches.'.$b->id.'.line', $b->line) }}">
            </td>

            <td>
              <input class="form-control form-control-sm" 
                     name="batches[{{ $b->id }}][cust_name]" 
                     value="{{ old('batches.'.$b->id.'.cust_name', $b->cust_name) }}">
            </td>

            <td>
              <input class="form-control form-control-sm" 
                     name="batches[{{ $b->id }}][weight_per_pc]" 
                     value="{{ old('batches.'.$b->id.'.weight_per_pc', $b->weight_per_pc) }}">
            </td>

            <td>
              <input class="form-control form-control-sm" 
                     name="batches[{{ $b->id }}][batch_qty]" 
                     value="{{ old('batches.'.$b->id.'.batch_qty', $b->batch_qty) }}">
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>

      <button class="btn btn-primary mt-3">Simpan Perubahan</button>

    </div>
  </div>
</form>

@endsection
