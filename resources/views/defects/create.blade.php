{{-- resources/views/defects/create.blade.php --}}
@extends('layouts.app')

@section('content')
<h4 class="fw-bold mb-3 d-flex align-items-center">
  <span>Input Kerusakan (Parent–Child)</span>
  <small class="ms-3 text-muted">Preview batch: <span id="batchCodePreview">-</span></small>
</h4>

<div class="card mb-3">
  <div class="card-body">
    <form method="POST" action="{{ route('defects.store') }}" id="defectForm" autocomplete="off">
      @csrf

      {{-- Header: date + department --}}
      <div class="row g-3 mb-3">
        <div class="col-md-3">
          <label class="form-label">Tanggal</label>
          <input id="dateInput" type="date" name="date" class="form-control" required
                 value="{{ old('date', now()->toDateString()) }}">
        </div>

        <div class="col-md-4">
          <label class="form-label">Departemen</label>
          <select id="departmentSelect" name="department_id" class="form-select" required>
            <option value="">— Pilih Departemen —</option>
            @foreach($departments as $dep)
              <option value="{{ $dep->id }}" data-name="{{ $dep->name }}"
                @if(old('department_id') == $dep->id) selected @endif>
                {{ $dep->name }}
              </option>
            @endforeach
          </select>
        </div>
      </div>

      {{-- Lines container (initial rows count: 5) --}}
      <div id="lines" class="vstack gap-2">
        @for ($i = 0; $i < 5; $i++)
        <div class="line-row p-3 rounded-3 border bg-white position-relative" data-wired="0">
          <div class="row g-3 align-items-end row-header">

            <div class="col-md-2 position-relative">
              <label class="form-label">Heat No.</label>
              <input
                type="text"
                class="form-control heat"
                name="lines[{{ $i }}][heat_number]"
                placeholder="cth: H240901"
                autocomplete="off"
                value="{{ old("lines.$i.heat_number", '') }}"
              >
            </div>

            <div class="col-md-2">
              <label class="form-label">Item Code</label>
              <input type="text" class="form-control item-code" name="lines[{{ $i }}][item_code]" readonly value="{{ old("lines.$i.item_code", '') }}">
            </div>

            <div class="col-md-3">
              <label class="form-label">Item Name</label>
              <input type="text" class="form-control item-name" name="lines[{{ $i }}][item_name]" readonly value="{{ old("lines.$i.item_name", '') }}">
            </div>

            <div class="col-md-1">
              <label class="form-label">AISI</label>
              <input type="text" class="form-control aisi" name="lines[{{ $i }}][aisi]" readonly value="{{ old("lines.$i.aisi", '') }}">
            </div>

            <div class="col-md-1">
              <label class="form-label">Size</label>
              <input type="text" class="form-control size" name="lines[{{ $i }}][size]" readonly value="{{ old("lines.$i.size", '') }}">
            </div>

            <div class="col-md-2">
              <label class="form-label">Line</label>
              <input type="text" class="form-control line" name="lines[{{ $i }}][line]" readonly value="{{ old("lines.$i.line", '') }}">
            </div>

            <div class="col-md-2">
              <label class="form-label">Cust</label>
              <input type="text" class="form-control cust_name" name="lines[{{ $i }}][cust_name]" readonly value="{{ old("lines.$i.cust_name", '') }}">
            </div>

            <div class="col-md-1">
              <label class="form-label">Qty PCS</label>
              <input class="form-control" type="number" min="0" name="lines[{{ $i }}][qty_pcs]" placeholder="0" value="{{ old("lines.$i.qty_pcs", '') }}">
            </div>

            <div class="col-md-1">
              <label class="form-label">Qty KG</label>
              <input class="form-control" type="number" min="0" step="0.001" name="lines[{{ $i }}][qty_kg]" placeholder="0.000" value="{{ old("lines.$i.qty_kg", '') }}">
            </div>

            <div class="col-md-3 mt-2">
              <label class="form-label">Kategori</label>
              <select class="form-select category" name="lines[{{ $i }}][defect_type_id]">
                <option value="">— Pilih Kategori —</option>
                @foreach($types->where('parent_id', null) as $t)
                  <option value="{{ $t->id }}" @if(old("lines.$i.defect_type_id") == $t->id) selected @endif>{{ $t->name }}</option>
                @endforeach
              </select>
            </div>

          </div>

          {{-- hidden batch_code per line (filled by JS or server) --}}
          <input type="hidden" name="lines[{{ $i }}][batch_code]" value="{{ old("lines.$i.batch_code", '') }}" class="batch-code-hidden">

          <div class="d-flex justify-content-end mt-2 gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm add-line">+ Tambah Baris</button>
            <button type="button" class="btn btn-outline-danger btn-sm remove-line">Hapus</button>
          </div>
        </div>
        @endfor
      </div>

      {{-- Submit --}}
      <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary px-4">Simpan Draft</button>
        <a href="{{ route('defects.index') }}" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // ---------- Utilities ----------
  const debounce = (fn, wait = 275) => {
    let t;
    return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
  };

  function escapeHtml(s) {
    if (!s) return '';
    return String(s).replace(/[&<>"'`=\/]/g, c => '&#' + c.charCodeAt(0) + ';');
  }

  // ---------- Endpoints ----------
  const heatUrl = "{{ route('api.heat') }}";               // ?prefix=...
  const nextCodeUrl = "{{ route('api.nextBatchCode') }}";  // ?departemen=...&date=...
  const itemInfoUrl = "{{ route('api.itemInfo') }}";       // ?heat=...

  // simple cache
  const nextCodeCache = {};

  async function fetchNextBatchCode(deptName, date) {
    if (!deptName) return null;
    const key = `${deptName}::${date}`;
    if (nextCodeCache[key]) return nextCodeCache[key];
    try {
      const url = new URL(nextCodeUrl, window.location.origin);
      url.searchParams.set('departemen', deptName);
      url.searchParams.set('date', date);
      const res = await fetch(url.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
      if (!res.ok) return null;
      const j = await res.json();
      if (j && j.status === 'ok' && j.code) {
        nextCodeCache[key] = j.code;
        return j.code;
      }
    } catch (e) {
      console.error('fetchNextBatchCode', e);
    }
    return null;
  }

  async function fetchHeatSuggestions(prefix) {
    if (!prefix || prefix.length < 1) return [];
    try {
      const url = new URL(heatUrl, window.location.origin);
      url.searchParams.set('prefix', prefix);
      const res = await fetch(url.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
      if (!res.ok) return [];
      const j = await res.json();
      return (j && Array.isArray(j.data)) ? j.data : [];
    } catch (e) {
      console.error('fetchHeatSuggestions', e);
      return [];
    }
  }

  async function fetchItemInfoByHeat(heat) {
    if (!heat) return null;
    try {
      const url = new URL(itemInfoUrl, window.location.origin);
      url.searchParams.set('heat', heat);
      const res = await fetch(url.toString(), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
      if (!res.ok) return null;
      const j = await res.json();
      if (!j) return null;
      // prefer {status: 'ok', data: {...}}
      if (j.status === 'ok') return j.data || null;
      return j.data || null;
    } catch (e) {
      console.error('fetchItemInfoByHeat', e);
    }
    return null;
  }

  // ---------- Suggestion UI helpers ----------
  function ensureSuggestBox(rowEl) {
    let box = rowEl.querySelector('.heat-suggest');
    if (!box) {
      box = document.createElement('div');
      box.className = 'heat-suggest border rounded-3 bg-white position-absolute shadow-sm';
      box.style.zIndex = 10000;
      box.style.left = '0';
      box.style.right = '0';
      box.style.top = '100%';
      box.style.display = 'none';
      const heatParent = rowEl.querySelector('.col-md-2.position-relative') || rowEl.querySelector('.col-md-2');
      if (heatParent) {
        heatParent.style.position = 'relative';
        heatParent.appendChild(box);
      } else {
        rowEl.appendChild(box);
      }
    }
    return box;
  }

  function renderSuggestions(box, items, onPick) {
    if (!box) return;
    if (!items || items.length === 0) {
      box.style.display = 'none';
      box.innerHTML = '';
      return;
    }
    let html = '<div class="list-group list-group-flush">';
    items.forEach(it => {
      html += `<button type="button" class="list-group-item list-group-item-action py-2 small"
                        data-heat="${escapeHtml(it.heat_number)}"
                        data-item="${escapeHtml(it.item_code)}"
                        data-batch="${escapeHtml(it.batch_code ?? '')}">
                <div class="fw-semibold">${escapeHtml(it.heat_number)} <span class="text-muted">/ ${escapeHtml(it.item_code)}</span></div>
                <div class="text-muted small">${escapeHtml(it.item_name ?? '')}</div>
              </button>`;
    });
    html += '</div>';
    box.innerHTML = html;
    box.style.display = 'block';
    box.querySelectorAll('button').forEach(btn => {
      btn.addEventListener('click', function () {
        const h = this.getAttribute('data-heat');
        const i = this.getAttribute('data-item');
        const b = this.getAttribute('data-batch');
        onPick(h, i, b);
      });
    });
  }

  // ---------- Row wiring ----------
  function wireRow(rowEl) {
    if (!rowEl || rowEl.dataset.wired === '1') return;
    rowEl.dataset.wired = '1';

    const heatInput = rowEl.querySelector('.heat');
    const itemInput = rowEl.querySelector('.item-code');
    const itemNameInput = rowEl.querySelector('.item-name');
    const aisiInput = rowEl.querySelector('.aisi');
    const sizeInput = rowEl.querySelector('.size');
    const lineInput = rowEl.querySelector('.line');
    const custInput = rowEl.querySelector('.cust_name');
    const cat = rowEl.querySelector('.category');
    const suggestBox = ensureSuggestBox(rowEl);

    if (cat) {
      // placeholder for future: populate subcategories if needed
      cat.addEventListener('change', () => {}, { passive: true });
    }

    if (heatInput) {
      const doFetchSuggest = debounce(async function () {
        const q = heatInput.value.trim();
        if (q.length < 1) { renderSuggestions(suggestBox, [], ()=>{}); return; }
        const items = await fetchHeatSuggestions(q);
        renderSuggestions(suggestBox, items, async function (heat, item, batch) {
          heatInput.value = heat;
          if (itemInput) itemInput.value = item || '';
          if (batch) {
            heatInput.dataset.batchCode = batch;
            setLineBatchCode(rowEl, batch);
          }
          renderSuggestions(suggestBox, [], ()=>{});
          // fetch full item info
          const info = await fetchItemInfoByHeat(heat);
          if (info) populateInfoToRow(rowEl, info);
        });
      }, 250);

      heatInput.addEventListener('input', doFetchSuggest);
      heatInput.addEventListener('focus', doFetchSuggest);

      // on change/blur try to fill fields
      heatInput.addEventListener('change', debounce(async function () {
        const h = heatInput.value.trim();
        if (!h) {
          clearDependentFields(rowEl);
          setLineBatchCode(rowEl, '');
          return;
        }
        const info = await fetchItemInfoByHeat(h);
        if (info) populateInfoToRow(rowEl, info);
      }, 200));

      // click outside to hide suggestions
      document.addEventListener('click', function (ev) {
        if (suggestBox && !suggestBox.contains(ev.target) && ev.target !== heatInput) {
          suggestBox.style.display = 'none';
        }
      });
    }

    // add/remove buttons
    const addBtn = rowEl.querySelector('.add-line');
    if (addBtn) addBtn.addEventListener('click', () => addNewLineRow(rowEl), { passive: true });

    const removeBtn = rowEl.querySelector('.remove-line');
    if (removeBtn) removeBtn.addEventListener('click', () => removeLineRow(rowEl), { passive: true });
  }

  function populateInfoToRow(rowEl, info) {
    const itemInput = rowEl.querySelector('.item-code');
    const itemNameInput = rowEl.querySelector('.item-name');
    const aisiInput = rowEl.querySelector('.aisi');
    const sizeInput = rowEl.querySelector('.size');
    const lineInput = rowEl.querySelector('.line');
    const custInput = rowEl.querySelector('.cust_name');
    const heatInput = rowEl.querySelector('.heat');

    if (itemInput && info.item_code) itemInput.value = info.item_code;
    if (itemNameInput && info.item_name) itemNameInput.value = info.item_name;
    if (aisiInput && info.aisi) aisiInput.value = info.aisi;
    if (sizeInput && info.size) sizeInput.value = info.size;
    if (lineInput && info.line) lineInput.value = info.line;
    if (custInput && info.cust_name) custInput.value = info.cust_name;

    if (heatInput && info.batch_code) {
      heatInput.dataset.batchCode = info.batch_code;
      setLineBatchCode(rowEl, info.batch_code);
    }
  }

  function clearDependentFields(rowEl) {
    ['.item-code', '.item-name', '.aisi', '.size', '.line', '.cust_name'].forEach(sel=>{
      const el = rowEl.querySelector(sel);
      if (el && el.hasAttribute('readonly')) el.value = '';
      else if (el) el.value = '';
    });
  }

  function getRowIndex(rowEl) {
    const anyNamed = rowEl.querySelector('[name]');
    if (!anyNamed) return 0;
    const m = anyNamed.name.match(/lines\[(\d+)\]\[/);
    return m ? parseInt(m[1], 10) : 0;
  }

  function setLineBatchCode(rowEl, code) {
    const hid = rowEl.querySelector('input.batch-code-hidden');
    const idx = getRowIndex(rowEl);
    if (hid) {
      hid.name = `lines[${idx}][batch_code]`;
      hid.value = code || '';
    } else {
      const newH = document.createElement('input');
      newH.type = 'hidden';
      newH.className = 'batch-code-hidden';
      newH.name = `lines[${idx}][batch_code]`;
      newH.value = code || '';
      rowEl.appendChild(newH);
    }

    // show a small badge for visual feedback
    let badge = rowEl.querySelector('.badge-batch-code');
    if (!badge) {
      badge = document.createElement('span');
      badge.className = 'badge bg-secondary badge-batch-code ms-2';
      const header = rowEl.querySelector('.row-header') || rowEl;
      header.insertBefore(badge, header.firstChild);
    }
    badge.textContent = code || '';
    if (!code && badge) badge.remove();
  }

  function addNewLineRow(fromRow) {
    const container = document.getElementById('lines');
    if (!container) return;
    const idx = container.querySelectorAll('.line-row').length;
    const clone = fromRow.cloneNode(true);
    clone.dataset.wired = '0';

    // clear values & update names
    clone.querySelectorAll('input,select,textarea').forEach(el => {
      if (el.name) el.name = el.name.replace(/\[\d+\]/g, '[' + idx + ']');

      if (el.tagName === 'SELECT') {
        el.selectedIndex = 0;
      } else if (el.type === 'hidden') {
        if (el.classList.contains('batch-code-hidden')) el.value = '';
      } else {
        el.value = '';
        if (el.hasAttribute('checked')) el.removeAttribute('checked');
      }
      if (el.dataset) delete el.dataset.batchCode;
    });

    const oldSuggest = clone.querySelector('.heat-suggest'); if (oldSuggest) oldSuggest.remove();
    const oldBadge = clone.querySelector('.badge-batch-code'); if (oldBadge) oldBadge.remove();

    container.appendChild(clone);
    wireRow(clone);
    // try assign batch code for new row
    assignBatchCodeToNewRow(clone);
  }

  function removeLineRow(rowEl) {
    const container = document.getElementById('lines');
    const rows = container.querySelectorAll('.line-row');
    if (rows.length <= 1) {
      // reset fields instead of removing last
      rowEl.querySelectorAll('input,select,textarea').forEach(el=>{
        if (el.tagName === 'INPUT') {
          if (el.type === 'number') el.value = null; else el.value = '';
        } else if (el.tagName === 'SELECT') el.selectedIndex = 0;
      });
      const hid = rowEl.querySelector('input.batch-code-hidden'); if (hid) hid.value = '';
      const badge = rowEl.querySelector('.badge-batch-code'); if (badge) badge.remove();
      return;
    }
    rowEl.remove();
    reindexAllRows();
  }

  async function assignBatchCodeToNewRow(rowEl) {
    const heatInput = rowEl.querySelector('.heat');
    if (heatInput && heatInput.dataset && heatInput.dataset.batchCode) {
      setLineBatchCode(rowEl, heatInput.dataset.batchCode);
      return;
    }
    const { dept, date } = getSelectedDeptAndDate();
    if (!dept) return;
    const code = await fetchNextBatchCode(dept, date);
    if (code) setLineBatchCode(rowEl, code);
  }

  function isRowFilled(row) {
    const heat = (row.querySelector('.heat') || {}).value || '';
    const item = (row.querySelector('.item-code') || {}).value || '';
    const pcs = parseFloat((row.querySelector('input[name*="[qty_pcs]"]') || {}).value || 0);
    const type = (row.querySelector('select[name*="[defect_type_id]"]') || {}).value || '';
    return (heat.trim() !== '' || item.trim() !== '' || pcs > 0 || (type && type !== ''));
  }

  function reindexAllRows() {
    const rows = Array.from(document.querySelectorAll('#lines .line-row'));
    rows.forEach((row, idx) => {
      row.querySelectorAll('[name]').forEach(el => {
        el.name = el.name.replace(/lines\[\d+\]/, `lines[${idx}]`);
      });
      const hid = row.querySelector('input.batch-code-hidden');
      if (hid) hid.name = `lines[${idx}][batch_code]`;
      const badge = row.querySelector('.badge-batch-code');
      if (badge && badge.textContent === '') badge.remove();
    });
  }

  // Before submit: remove empty rows, reindex names
  const form = document.getElementById('defectForm');
  form.addEventListener('submit', function (ev) {
    const container = document.getElementById('lines');
    const rows = Array.from(container.querySelectorAll('.line-row'));
    const filled = rows.filter(isRowFilled);

    if (filled.length === 0) {
      if (!confirm('Tidak ada baris terisi. Tetap ingin menyimpan draft kosong?')) {
        ev.preventDefault();
        return;
      }
    }

    // remove empty rows
    rows.forEach(r => { if (!isRowFilled(r)) r.remove(); });

    // reindex remaining rows
    reindexAllRows();
    // allow submit
  });

  // ---------- initial wiring ----------
  document.querySelectorAll('.line-row').forEach(wireRow);

  // department/date watchers for preview
  function getSelectedDeptAndDate() {
    const depSel = document.getElementById('departmentSelect');
    const dateInput = document.getElementById('dateInput');
    if (!depSel) return { dept: null, date: (dateInput ? dateInput.value : new Date().toISOString().slice(0,10)) };
    const selected = depSel.options[depSel.selectedIndex];
    const deptName = selected ? (selected.dataset.name || selected.value) : null;
    return { dept: deptName, date: dateInput ? dateInput.value : new Date().toISOString().slice(0,10) };
  }

  async function updateBatchPreview() {
    const preview = document.getElementById('batchCodePreview');
    const { dept, date } = getSelectedDeptAndDate();
    if (!dept) { preview.textContent = '-'; return; }
    const code = await fetchNextBatchCode(dept, date);
    preview.textContent = code || '-';
  }

  const depEl = document.getElementById('departmentSelect');
  const dateEl = document.getElementById('dateInput');
  if (depEl) depEl.addEventListener('change', debounce(updateBatchPreview, 300));
  if (dateEl) dateEl.addEventListener('change', debounce(updateBatchPreview, 300));
  updateBatchPreview();

  // Expose a helper if other scripts want to set batch code programmatically
  window.__deftrack_fillBatchCodeFromHeatSelection = function (heatInputEl, batchObj) {
    if (!heatInputEl || !batchObj) return;
    heatInputEl.dataset.batchCode = batchObj.batch_code || '';
    const row = heatInputEl.closest('.line-row');
    if (row) setLineBatchCode(row, batchObj.batch_code || '');
  };

});
</script>
@endpush
