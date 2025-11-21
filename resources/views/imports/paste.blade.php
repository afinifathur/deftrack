{{-- resources/views/imports/paste.blade.php --}}
@extends('layouts.app')

@section('content')
<h4 class="fw-bold mb-3">Import Batch — Paste dari Excel</h4>

<div class="card mb-3">
  <div class="card-body">
    <div class="row mb-3">
      <div class="col-md-3">
        <label class="form-label">Tanggal</label>
        <input id="dateInput" type="date" class="form-control" value="{{ now()->toDateString() }}">
      </div>
      <div class="col-md-4">
        <label class="form-label">Departemen</label>
        <select id="departmentSelect" class="form-select">
          <option value="">— Pilih Departemen —</option>
          @foreach($departments as $d)
            <option value="{{ $d->id }}">{{ $d->name }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div id="hotContainer"></div>

    <div class="mt-3">
      <button id="btnValidate" class="btn btn-outline-primary">Validasi Header & Data</button>
      <button id="btnImport" class="btn btn-primary" disabled>Simpan Import</button>
      <span id="statusText" class="ms-3"></span>
    </div>

    <p class="text-muted mt-2">Cara: copy dari Excel (9 kolom berurutan) → pilih sel pertama → Ctrl+V</p>
  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/deftrack/handsontable/handsontable.full.min.css') }}">
<style>
  #hotContainer { width:100%; height: 420px; border: 1px solid #e6e6e6; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('vendor/deftrack/jquery/jquery-3.7.1.min.js') }}"></script>
<script src="{{ asset('vendor/deftrack/handsontable/handsontable.full.min.js') }}"></script>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const container = document.getElementById('hotContainer');

  const expectedHeaders = ['heat_number','item_code','item_name','weight_per_pc','batch_qty','aisi','size','line','cust_name'];

  const hot = new Handsontable(container, {
    data: Handsontable.helper.createSpreadsheetData(10, expectedHeaders.length),
    rowHeaders: true,
    colHeaders: expectedHeaders.map(h => h.replace(/_/g,' ').toUpperCase()),
    contextMenu: true,
    stretchH: 'all',
    width: '100%',
    height: 420,
    licenseKey: 'non-commercial-and-evaluation',
    manualColumnResize: true,
    manualRowResize: true,
    pasteMode: 'shift_down',
    // allow empty cells; validation comes later
    columns: [
      {type: 'text'}, // heat_number
      {type: 'text'}, // item_code
      {type: 'text'}, // item_name
      {type: 'numeric', numericFormat: { pattern: '0.000' }}, // weight
      {type: 'numeric', numericFormat: { pattern: '0' }}, // batch_qty
      {type: 'text'}, // aisi
      {type: 'text'}, // size
      {type: 'text'}, // line
      {type: 'text'}  // cust_name
    ],
  });

  function extractData() {
    const data = hot.getData();
    // trim rows: drop fully empty rows
    const rows = data.map(r => r.map(c => (c === null || c === undefined) ? '' : (''+c).trim()));
    const filtered = rows.filter(r => {
      return r.some(c => c !== '');
    });
    return filtered;
  }

  function validateHeadersFromFirstRow() {
    const firstRow = hot.getDataAtRow(0).map(c => (c||'').toString().trim().toLowerCase().replace(/\s+/g,'_'));
    // if first row looks like header (has 'heat' or 'item_code'), signal it
    const looksHeader = firstRow.includes('heat_number') || firstRow.includes('heat') || firstRow.includes('item_code');
    return looksHeader;
  }

  $('#btnValidate').on('click', function(){
    const rows = extractData();
    const hasHeader = validateHeadersFromFirstRow();
    $('#statusText').text('');

    if (rows.length === 0) {
      $('#statusText').text('Tabel kosong. Paste dulu data dari Excel.').css('color','red');
      $('#btnImport').prop('disabled', true);
      return;
    }

    // if first row is header, show tip and ask user to remove or leave it (server handles it)
    if (hasHeader) {
      $('#statusText').text('Terlihat header di baris pertama. Server akan mengabaikannya saat import.').css('color','green');
    } else {
      $('#statusText').text('Header tidak terdeteksi — pastikan urutan kolom: ' + expectedHeaders.join(', ')).css('color','orange');
    }

    // basic column count check
    const first = rows[0] || [];
    if (first.length < expectedHeaders.length) {
      $('#statusText').append(' Kolom kurang: perlu '+expectedHeaders.length);
      $('#btnImport').prop('disabled', true);
      return;
    }

    $('#btnImport').prop('disabled', false);
  });

  $('#btnImport').on('click', function(){
    const rows = extractData();
    const hasHeader = validateHeadersFromFirstRow();

    // if header present, server code will drop it; still send data as-is
    const payload = {
      date: document.getElementById('dateInput').value,
      department_id: document.getElementById('departmentSelect').value,
      data: rows
    };

    $(this).prop('disabled', true).text('Menyimpan...');
    $('#statusText').text('Mengirim data...').css('color','black');

    $.ajax({
      url: "{{ route('imports.paste.store') }}",
      method: 'POST',
      headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
      contentType: 'application/json',
      data: JSON.stringify(payload),
      dataType: 'json'
    }).done(function(res){
      if (res.status === 'ok') {
        $('#statusText').text('Selesai. Insert: '+res.ins+' Update: '+res.upd).css('color','green');
        // optionally redirect to import detail
        window.location = "{{ url('imports') }}";
      } else {
        $('#statusText').text('Error: '+(res.message||'')).css('color','red');
      }
    }).fail(function(xhr){
      const msg = xhr.responseJSON?.message || (xhr.responseJSON?.errors?.join('; ') || 'Server error');
      $('#statusText').text('Gagal: '+msg).css('color','red');
      $('#btnImport').prop('disabled', false).text('Simpan Import');
    });
  });

}); // DOMContentLoaded
</script>
@endpush
