{{-- resources/views/imports/create.blade.php --}}
@extends('layouts.app')

@section('content')
<h4 class="fw-bold mb-3">Import Master Batch (CSV) / Paste dari Excel</h4>

<div class="card mb-3">
  <div class="card-body">

    {{-- Tabs --}}
    <ul class="nav nav-tabs mb-3" id="importTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-file" data-bs-toggle="tab"
                data-bs-target="#pane-file" type="button" role="tab">
          Upload File (CSV)
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-paste" data-bs-toggle="tab"
                data-bs-target="#pane-paste" type="button" role="tab">
          Paste dari Excel
        </button>
      </li>
    </ul>

    <div class="tab-content">
      {{-- Pane: file upload --}}
      <div class="tab-pane fade" id="pane-file" role="tabpanel" aria-labelledby="tab-file">
        <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data">
          @csrf
          <div class="row g-3 mb-3">
            <div class="col-md-3">
              <label class="form-label">Tanggal</label>
              <input
                type="date"
                name="date"
                class="form-control"
                value="{{ old('date', now()->toDateString()) }}"
                required
              >
            </div>
            <div class="col-md-3">
              <label class="form-label">Departemen</label>
              <select name="department_id" class="form-select" required>
                <option value="">— Pilih Departemen —</option>
                @foreach($departments as $dep)
                  <option value="{{ $dep->id }}">{{ $dep->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">File (CSV)</label>
              <input type="file" name="file" accept=".csv,text/csv" class="form-control" required>
              <small class="text-muted">
                Kolom minimal: heat_number, item_code, item_name, weight_per_pc, batch_qty — tambahan:
                aisi,size,line,cust_name
              </small>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Catatan (opsional)</label>
            <input type="text" name="note" class="form-control" value="">
          </div>

          <button class="btn btn-primary">Upload</button>
        </form>
      </div>

      {{-- Pane: paste spreadsheet --}}
      <div class="tab-pane fade show active" id="pane-paste" role="tabpanel" aria-labelledby="tab-paste">
        <div class="row g-3 mb-3">
          <div class="col-md-3">
            <label class="form-label">Tanggal</label>
            <input id="pasteDate" type="date" class="form-control" value="{{ now()->toDateString() }}">
          </div>
          <div class="col-md-3">
            <label class="form-label">Departemen</label>
            <select id="pasteDept" class="form-select">
              <option value="">— Pilih Departemen —</option>
              @foreach($departments as $dep)
                <option value="{{ $dep->id }}" data-name="{{ $dep->name }}">{{ $dep->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-6 d-flex align-items-end">
            <div>
              <small class="text-muted">
                Paste dari Excel: urutkan kolom sesuai template →
                Heat | Item Code | Item Name | Weight_per_pc | Batch_qty | AISI | Size | Line | Cust_name
              </small>
            </div>
          </div>
        </div>

        {{-- GRID --}}
        <div class="mt-3" id="hotContainer"></div>

        <div class="mt-3 d-flex align-items-center gap-2 flex-wrap">
          <button id="btnValidatePaste" class="btn btn-outline-primary">Preview / Validasi</button>
          <button id="btnImportPaste" class="btn btn-primary" disabled>Import dari Paste</button>
          <a id="cancelPreview" class="btn btn-outline-secondary d-none"
             href="#" onclick="location.reload();return false;">Batal</a>
          <span id="pasteStatus" class="ms-2 small"></span>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('vendor/deftrack/handsontable/handsontable.full.min.css') }}">
<style>
  #hotContainer {
    border: 1px solid #e9ecef;
    border-radius: 6px;
    background: #fff;
    height: 360px;        /* tinggi fix + scroll internal */
    overflow: hidden;
  }

  .handsontable .htCore {
    font-size: 12px;
  }

  .handsontable .htCore td,
  .handsontable .htCore th {
    padding: 4px 6px;
  }

  .handsontable .ht_clone_top .htCore thead th {
    font-weight: 700;
    text-transform: uppercase;
    font-size: 12px;
    padding: 6px 8px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
  }

  .handsontable .htCore .htRowHeader {
    width: 36px !important;
    font-size: 11px;
    text-align: center;
  }

  /* Zebra pakai class (paling aman) */
  .ht-zebra-even {
    background-color: #ffffff;
  }
  .ht-zebra-odd {
    background-color: #f5f5f5;
  }

  /* Saat cell terpilih / area diseleksi */
  .handsontable .htCore td.current,
  .handsontable .htCore td.area {
    background-color: #cfe2ff !important;
  }

  .htContextMenu,
  .htDropdownMenu {
    display: none !important;
  }
  /* Samakan border & background di header kiri supaya garisnya nyambung rapi */
  .handsontable .ht_clone_left .htCore thead th,
  .handsontable .ht_clone_left .htCore tbody th {
    background-color: #f8f9fa;              /* sama seperti header atas */
    border-right: 1px solid #dee2e6 !important;
  }

  /* Hilangkan gap aneh di perbatasan clone kiri */
  #hotContainer .ht_clone_left .wtHolder {
    overflow: hidden !important;
  }
</style>
@endpush

@push('scripts')
<script src="{{ asset('vendor/deftrack/jquery/jquery-3.7.1.min.js') }}"></script>
<script src="{{ asset('vendor/deftrack/handsontable/handsontable.full.min.js') }}"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
  const expectedHeaders = [
    'heat_number',
    'item_code',
    'item_name',
    'weight_per_pc',
    'batch_qty',
    'aisi',
    'size',
    'line',
    'cust_name'
  ];

  // mulai dengan grid kosong tapi punya banyak baris siap isi
  const emptyData = [];
  for (let r = 0; r < 200; r++) {
    emptyData.push(new Array(expectedHeaders.length).fill(''));
  }

  const container = document.getElementById('hotContainer');

  const hot = new Handsontable(container, {
    data: emptyData,
    colHeaders: expectedHeaders.map(h => h.replace(/_/g, ' ').toUpperCase()),
    rowHeaders: true,
    stretchH: 'all',
    height: 360,
    manualColumnResize: true,
    manualRowResize: true,
    contextMenu: false,
    dropdownMenu: false,
    colWidths: [110, 110, 260, 90, 90, 90, 90, 90, 140],
    columns: [
      {type: 'text'},                                   // heat_number
      {type: 'text'},                                   // item_code
      {type: 'text'},                                   // item_name
      {type: 'numeric', numericFormat: {pattern: '0.000'}}, // weight_per_pc
      {type: 'numeric', numericFormat: {pattern: '0'}},     // batch_qty
      {type: 'text'},                                   // aisi
      {type: 'text'},                                   // size
      {type: 'text'},                                   // line
      {type: 'text'}                                    // cust_name
    ],
    // zebra row pakai className
    cells: function (row, col) {
      const props = {};
      props.className = (row % 2 === 0) ? 'ht-zebra-even' : 'ht-zebra-odd';
      return props;
    },
    licenseKey: 'non-commercial-and-evaluation'
  });

  function extractRows() {
    const data = hot.getData();
    return data
      .map(row => row.map(c => (c == null ? '' : String(c).trim())))
      .filter(row => row.some(c => c !== ''));
  }

  function looksLikeHeader(row) {
    const lower = row.map(c => (c || '').toLowerCase().replace(/\s+/g, '_'));
    return lower.includes('heat_number') ||
           lower.includes('heat') ||
           lower.includes('item_code');
  }

  $('#btnValidatePaste').on('click', function () {
    const rows = extractRows();
    $('#pasteStatus').css('color', 'black');

    if (!rows.length) {
      $('#pasteStatus').text('Tabel kosong — paste dulu dari Excel.').css('color', 'red');
      $('#btnImportPaste').prop('disabled', true);
      return;
    }

    const first = rows[0];

    if (looksLikeHeader(first)) {
      $('#pasteStatus')
        .text('Header terdeteksi di baris pertama — server akan mengabaikannya saat import. Klik Import bila ok.')
        .css('color', 'green');
    } else {
      $('#pasteStatus')
        .text('Tidak terdeteksi header. Pastikan urutan kolom sesuai: ' + expectedHeaders.join(', '))
        .css('color', 'orange');
    }

    if (first.length < expectedHeaders.length) {
      $('#pasteStatus')
        .append(' (Kolom kurang — perlu ' + expectedHeaders.length + ')')
        .css('color', 'red');
      $('#btnImportPaste').prop('disabled', true);
      return;
    }

    $('#btnImportPaste').prop('disabled', false);
    $('#cancelPreview').removeClass('d-none');
  });

  $('#btnImportPaste').on('click', function () {
    const rows = extractRows();
    const payload = {
      date: document.getElementById('pasteDate').value,
      department_id: document.getElementById('pasteDept').value,
      data: rows
    };

    if (!payload.date || !payload.department_id) {
      $('#pasteStatus').text('Tanggal dan Departemen harus diisi.').css('color', 'red');
      return;
    }

    $('#btnImportPaste').prop('disabled', true).text('Menyimpan...');
    $('#pasteStatus').text('Mengirim data ke server...');

    $.ajax({
      url: "{{ route('imports.paste.store') }}",
      method: 'POST',
      contentType: 'application/json',
      data: JSON.stringify(payload),
      headers: {'X-CSRF-TOKEN': '{{ csrf_token() }}'},
      dataType: 'json'
    }).done(function (res) {
      if (res.status === 'ok') {
        $('#pasteStatus')
          .text('Selesai. Insert: ' + res.ins + ' Update: ' + res.upd)
          .css('color', 'green');
        window.location.href = "{{ route('imports.index') }}";
      } else {
        $('#pasteStatus')
          .text('Gagal: ' + (res.message || JSON.stringify(res)))
          .css('color', 'red');
        $('#btnImportPaste').prop('disabled', false).text('Import dari Paste');
      }
    }).fail(function (xhr) {
      const msg =
        (xhr.responseJSON && xhr.responseJSON.message) ||
        (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.join('; ')) ||
        xhr.responseText ||
        'Server error';

      $('#pasteStatus').text('Gagal: ' + msg).css('color', 'red');
      $('#btnImportPaste').prop('disabled', false).text('Import dari Paste');
    });
  });

});
</script>
@endpush
