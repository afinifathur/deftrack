{{-- resources/views/reports/index.blade.php --}}
@extends('layouts.app')

@section('content')
<h5 class="fw-bold mb-3">Laporan & Export</h5>

<div class="card">
  <div class="card-body">
    <form class="row g-2 align-items-end" id="reportForm">
      <div class="col-auto">
        <label class="form-label">Dari</label>
        <input type="date" name="from" class="form-control" required>
      </div>
      <div class="col-auto">
        <label class="form-label">Sampai</label>
        <input type="date" name="to" class="form-control" required>
      </div>
      <div class="col-auto">
        <label class="form-label">Departemen</label>
        <select name="department_id" class="form-select">
          <option value="">Semua</option>
          @foreach($departments as $d)
            <option value="{{ $d->id }}">{{ $d->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-auto">
        <label class="form-label">Kategori (opsional)</label>
        <input type="text" name="type_name" class="form-control" placeholder="Nama kategori/subkategori">
      </div>

      <div class="col-auto">
        <button class="btn btn-outline-secondary" type="button" id="btnEstimate">Perkirakan Baris</button>
      </div>

      {{-- Bagian tombol export (PDF aktif) --}}
      <div class="col-auto ms-auto">
        <a class="btn btn-success" id="btnCsv" href="#">Export CSV</a>
        <a class="btn btn-primary" id="btnXlsx" href="#">Export XLSX</a>
        <a class="btn btn-outline-info" id="btnPdf" href="#">Export PDF</a>
      </div>
    </form>

    <div class="mt-2 small text-muted" id="estInfo">Estimator belum dijalankan.</div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  const form    = document.getElementById('reportForm');
  const estInfo = document.getElementById('estInfo');
  const btnCsv  = document.getElementById('btnCsv');
  const btnXlsx = document.getElementById('btnXlsx');
  const btnPdf  = document.getElementById('btnPdf');

  function qs() {
    const p = new URLSearchParams(new FormData(form));
    return p.toString();
  }

  function setLinks(){
    const query = qs();
    btnCsv.href  = "{{ route('reports.exportCsv') }}"  + "?" + query;
    btnXlsx.href = "{{ route('reports.exportXlsx') }}" + "?" + query;
    btnPdf.href  = "{{ route('reports.exportPdf') }}"  + "?" + query; // NEW
  }

  setLinks();
  form.addEventListener('change', setLinks);
  form.addEventListener('input', setLinks);

  document.getElementById('btnEstimate').addEventListener('click', async function(){
    const res = await fetch("{{ route('reports.estimate') }}?"+qs(), { headers: { 'Accept':'application/json' }});
    const j = await res.json();
    if (j.error) {
      estInfo.textContent = "Error: " + j.error;
      return;
    }
    estInfo.textContent = `Perkiraan baris: ${j.count.toLocaleString()} (rekomendasi: ` +
      (j.count > 30000 ? 'XLSX' : 'CSV/XLSX') + `)`;
  });
})();
</script>
@endpush
