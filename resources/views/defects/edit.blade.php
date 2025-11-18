@extends('layouts.app')
@section('content')
<h4 class="mb-3">Edit Defect #{{ $defect->id }}</h4>

<form method="POST" action="{{ route('defects.update', $defect->id) }}">
  @csrf @method('PUT')

  <div class="card mb-3">
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-3">
          <label class="form-label">Tanggal</label>
          <input type="date" name="date" value="{{ old('date', \Carbon\Carbon::parse($defect->date)->toDateString()) }}" class="form-control" required>
        </div>
        <div class="col-md-4">
          <label class="form-label">Departemen</label>
          <select name="department_id" class="form-select" required>
            @foreach(\App\Models\Department::orderBy('name')->get() as $dep)
              <option value="{{ $dep->id }}" {{ $dep->id == $defect->department_id ? 'selected':'' }}>{{ $dep->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-md-5">
          <label class="form-label">Catatan</label>
          <input class="form-control" name="notes" value="{{ old('notes',$defect->notes) }}">
        </div>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <h6>Lines</h6>
      <table class="table table-sm">
        <thead class="table-light">
          <tr><th>Heat</th><th>Item</th><th>Kategori</th><th>Sub</th><th>PCS</th><th>KG</th></tr>
        </thead>
        <tbody>
          @foreach($defect->lines as $line)
          <tr>
            <td>{{ optional($line->batch)->heat_number }}</td>
            <td>{{ optional($line->batch)->item_code }}</td>
            <td>
              <select name="lines[{{ $line->id }}][defect_type_id]" class="form-select form-select-sm">
                <option value="">- Pilih -</option>
                @foreach($types as $t)
                  <option value="{{ $t->id }}" {{ $line->defect_type_id == $t->id ? 'selected':'' }}>{{ $t->name }}</option>
                @endforeach
              </select>
            </td>
            <td>
              <input type="text" name="lines[{{ $line->id }}][subtype_id]" class="form-control form-control-sm" value="{{ $line->subtype_id }}">
            </td>
            <td><input type="number" name="lines[{{ $line->id }}][qty_pcs]" class="form-control form-control-sm" value="{{ $line->qty_pcs }}"></td>
            <td><input type="number" step="0.001" name="lines[{{ $line->id }}][qty_kg]" class="form-control form-control-sm" value="{{ $line->qty_kg }}"></td>
          </tr>
          @endforeach
        </tbody>
      </table>

      <div class="mt-3">
        <button class="btn btn-primary">Simpan Perubahan</button>
        <a href="{{ route('defects.show', $defect->id) }}" class="btn btn-outline-secondary">Batal</a>
      </div>
    </div>
  </div>
</form>
@endsection
