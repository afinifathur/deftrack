@extends('layouts.app')
@section('content')
<h5>Input Kerusakan (Parent–Child)</h5>
<form method="POST" action="{{ route('defects.store') }}" id="defectForm">@csrf
<div class="row g-2 mb-2">
  <div class="col-md-3"><label class="form-label">Tanggal</label><input type="date" name="date" class="form-control" required value="{{ now()->toDateString() }}"></div>
  <div class="col-md-3"><label class="form-label">Departemen</label><select name="department_id" class="form-select" required>
  @foreach($departments as $d)<option value="{{ $d->id }}">{{ $d->name }}</option>@endforeach
  </select></div>
</div>
<div id="lines">
@for($i=0;$i<5;$i++)
<div class="row g-2 align-items-end mb-2 line-row">
  <div class="col-md-2"><input class="form-control heat" name="lines[{{ $i }}][heat_number]" placeholder="Heat No." required></div>
  <div class="col-md-2"><input class="form-control" name="lines[{{ $i }}][item_code]" placeholder="Item Code" required></div>
  <div class="col-md-3">
    <select class="form-select" name="lines[{{ $i }}][defect_type_id]" required>
      <option value="">- Kategori -</option>
      @foreach($types as $t)
        <optgroup label="{{ $t->name }}">
        @foreach($t->children as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
        </optgroup>
      @endforeach
    </select>
  </div>
  <div class="col-md-2"><input class="form-control" type="number" min="0" name="lines[{{ $i }}][qty_pcs]" placeholder="Qty PCS"></div>
  <div class="col-md-2"><input class="form-control" type="number" min="0" step="0.001" name="lines[{{ $i }}][qty_kg]" placeholder="Qty KG"></div>
  <div class="col-md-1"><button type="button" class="btn btn-outline-secondary btn-sm add-line">+Tambah</button></div>
</div>
@endfor
</div>
<button class="btn btn-primary mt-2">Simpan Draft</button>
</form>
@push('scripts')
<script>
document.querySelectorAll('.add-line').forEach(btn=>btn.addEventListener('click',function(){
  const lines=document.getElementById('lines'); const idx=lines.querySelectorAll('.line-row').length;
  const tpl=lines.querySelector('.line-row').cloneNode(true);
  tpl.querySelectorAll('input,select').forEach(el=>{ el.value=''; el.name=el.name.replace(/\[\d+\]/,`[${idx}]`); });
  lines.appendChild(tpl);
}));
</script>
@endpush
@endsection
