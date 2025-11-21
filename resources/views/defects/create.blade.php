{{-- resources/views/defects/create.blade.php --}}
@extends('layouts.app')

@section('content')
<h4 class="fw-bold mb-3 d-flex align-items-center">
    <span>Input Kerusakan (Parent–Child)</span>
    <small class="ms-3 text-muted">
        Preview batch: <span id="batchCodePreview">-</span>
    </small>
</h4>

<div class="card mb-3">
    <div class="card-body">
        <form method="POST"
              action="{{ route('defects.store') }}"
              id="defectForm"
              autocomplete="off">
            @csrf

            {{-- Header: date + department --}}
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label for="dateInput" class="form-label">Tanggal</label>
                    <input id="dateInput"
                           type="date"
                           name="date"
                           class="form-control"
                           required
                           value="{{ old('date', now()->toDateString()) }}">
                </div>

                <div class="col-md-4">
                    <label for="departmentSelect" class="form-label">Departemen</label>
                    <select id="departmentSelect"
                            name="department_id"
                            class="form-select"
                            required>
                        <option value="">— Pilih Departemen —</option>
                        @foreach($departments as $dep)
                            <option value="{{ $dep->id }}"
                                    data-name="{{ $dep->name }}"
                                    @selected(old('department_id') == $dep->id)>
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
                                <input type="text"
                                       class="form-control item-code"
                                       name="lines[{{ $i }}][item_code]"
                                       readonly
                                       value="{{ old("lines.$i.item_code", '') }}">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Item Name</label>
                                <input type="text"
                                       class="form-control item-name"
                                       name="lines[{{ $i }}][item_name]"
                                       readonly
                                       value="{{ old("lines.$i.item_name", '') }}">
                            </div>

                            <div class="col-md-1">
                                <label class="form-label">AISI</label>
                                <input type="text"
                                       class="form-control aisi"
                                       name="lines[{{ $i }}][aisi]"
                                       readonly
                                       value="{{ old("lines.$i.aisi", '') }}">
                            </div>

                            <div class="col-md-1">
                                <label class="form-label">Size</label>
                                <input type="text"
                                       class="form-control size"
                                       name="lines[{{ $i }}][size]"
                                       readonly
                                       value="{{ old("lines.$i.size", '') }}">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Line</label>
                                <input type="text"
                                       class="form-control line"
                                       name="lines[{{ $i }}][line]"
                                       readonly
                                       value="{{ old("lines.$i.line", '') }}">
                            </div>

                            <div class="col-md-2">
                                <label class="form-label">Cust</label>
                                <input type="text"
                                       class="form-control cust_name"
                                       name="lines[{{ $i }}][cust_name]"
                                       readonly
                                       value="{{ old("lines.$i.cust_name", '') }}">
                            </div>

                            <div class="col-md-1">
                                <label class="form-label">Qty PCS</label>
                                <input class="form-control"
                                       type="number"
                                       min="0"
                                       name="lines[{{ $i }}][qty_pcs]"
                                       placeholder="0"
                                       value="{{ old("lines.$i.qty_pcs", '') }}">
                            </div>

                            <div class="col-md-1">
                                <label class="form-label">Qty KG</label>
                                <input class="form-control qty-kg"
                                       type="number"
                                       min="0"
                                       step="0.001"
                                       name="lines[{{ $i }}][qty_kg]"
                                       placeholder="0.000"
                                       value="{{ old("lines.$i.qty_kg", '0.000') }}"
                                       readonly
                                       aria-readonly="true"
                                       title="Diisi otomatis dari Qty PCS × Berat per PC">
                            </div>

                            <div class="col-md-3 mt-2">
                                <label class="form-label">Kategori</label>
                                {{-- 
                                    Diisi DINAMIS lewat JS berdasarkan departemen:
                                    - JS di _line_category_js akan mem-fetch
                                      /api/departments/{department}/categories
                                      dan mengisi semua <select.category>.
                                --}}
                                <select class="form-select category"
                                        name="lines[{{ $i }}][defect_type_id]">
                                    <option value="">— Pilih Kategori —</option>
                                </select>
                            </div>

                        </div>

                        {{-- hidden batch_code per line (filled by JS or server) --}}
                        <input type="hidden"
                               name="lines[{{ $i }}][batch_code]"
                               value="{{ old("lines.$i.batch_code", '') }}"
                               class="batch-code-hidden">

                        <div class="d-flex justify-content-end mt-2 gap-2">
                            <button type="button"
                                    class="btn btn-outline-secondary btn-sm add-line">
                                + Tambah Baris
                            </button>
                            <button type="button"
                                    class="btn btn-outline-danger btn-sm remove-line">
                                Hapus
                            </button>
                        </div>
                    </div>
                @endfor
            </div>

            {{-- Submit --}}
            <div class="d-flex gap-2 mt-3">
                <button type="submit" class="btn btn-primary px-4">
                    Simpan Draft
                </button>
                <a href="{{ route('defects.index') }}" class="btn btn-outline-secondary">
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Debounce helper
    const debounce = (fn, wait = 200) => {
        let t;
        return (...args) => {
            clearTimeout(t);
            t = setTimeout(() => fn(...args), wait);
        };
    };

    const heatApi     = "{{ route('api.heat') }}";
    const itemInfoApi = "{{ route('api.itemInfo') }}";

    // tolerant fetch: returns data object or null
    async function fetchItemInfoByHeat(heat) {
        if (!heat) return null;
        try {
            const url = new URL(itemInfoApi, window.location.origin);
            url.searchParams.set('heat', heat);

            const res = await fetch(url.toString(), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            });

            if (!res.ok) return null;
            const j = await res.json();
            return j?.data ?? j ?? null;
        } catch (e) {
            console.error('fetchItemInfoByHeat error', e);
            return null;
        }
    }

    // helpers to find elements in a row (compatible with your blade)
    function findHeatEl(row) {
        return row.querySelector('.heat')
            || row.querySelector('input[name*="[heat"]')
            || row.querySelector('input[placeholder*="H"]');
    }
    function findQtyPcsEl(row) {
        return row.querySelector('.qty-pcs')
            || row.querySelector('input[name*="[qty_pcs]"]')
            || row.querySelector('input[type="number"][name*="qty_pcs"]');
    }
    function findQtyKgEl(row) {
        return row.querySelector('.qty-kg')
            || row.querySelector('input[name*="[qty_kg]"]')
            || row.querySelector('input[type="number"][name*="qty_kg"]');
    }

    function ensureWeightInput(row) {
        let hid = row.querySelector('.item-weight');
        if (!hid) {
            hid = document.createElement('input');
            hid.type = 'hidden';
            hid.className = 'item-weight';
            row.appendChild(hid);
        }
        return hid;
    }

    // calculate kg using hidden weight or dataset
    function calcKgForRow(row) {
        const pcsEl = findQtyPcsEl(row);
        const kgEl  = findQtyKgEl(row);
        if (!kgEl) return;

        const hid    = row.querySelector('.item-weight');
        const weight = parseFloat((hid && hid.value) || row.dataset.weight || 0) || 0;
        const pcs    = parseInt((pcsEl && pcsEl.value) || 0, 10) || 0;
        const kg     = +(pcs * weight);

        kgEl.value = kg.toFixed(3);
    }

    // populate row fields with API data (tolerant keys)
    function populateInfoToRow(row, info) {
        if (!info) return;

        const pick = (obj, keys) => {
            for (const k of keys) {
                if (Object.prototype.hasOwnProperty.call(obj, k) &&
                    obj[k] !== null &&
                    obj[k] !== undefined) {
                    return obj[k];
                }
            }
            return null;
        };

        const itemCode = pick(info, ['item_code', 'itemCode', 'code']);
        const itemName = pick(info, ['item_name', 'itemName', 'name']);
        const aisi     = pick(info, ['aisi', 'material']);
        const size     = pick(info, ['size']);
        const line     = pick(info, ['line']);
        const cust     = pick(info, ['cust_name', 'customer_name', 'cust']);
        const batch    = pick(info, ['batch_code', 'batchCode']);
        const weightV  = pick(info, ['weight_per_pc', 'weightPerPc', 'weight', 'wt_per_pc']);

        const itemInput     = row.querySelector('.item-code');
        const itemNameInput = row.querySelector('.item-name');
        const aisiInput     = row.querySelector('.aisi');
        const sizeInput     = row.querySelector('.size');
        const lineInput     = row.querySelector('.line');
        const custInput     = row.querySelector('.cust_name');
        const heatInput     = findHeatEl(row);

        if (itemInput && itemCode)     itemInput.value     = itemCode;
        if (itemNameInput && itemName) itemNameInput.value = itemName;
        if (aisiInput && aisi)         aisiInput.value     = aisi;
        if (sizeInput && size)         sizeInput.value     = size;
        if (lineInput && line)         lineInput.value     = line;
        if (custInput && cust)         custInput.value     = cust;

        if (heatInput && batch) {
            heatInput.dataset.batchCode = batch;
        }

        const weightNum = parseFloat(weightV ?? 0) || 0;
        const hid       = ensureWeightInput(row);

        hid.value          = weightNum;
        row.dataset.weight = weightNum;

        console.debug('populateInfoToRow: heat=', (heatInput && heatInput.value), 'weight_per_pc=', weightNum);

        // recalc kg if pcs present
        calcKgForRow(row);
    }

    // build suggestion box for heat input
    function ensureSuggestBox(row, heatEl) {
        let box = row.querySelector('.heat-suggest');
        if (!box) {
            box = document.createElement('div');
            box.className = 'heat-suggest border rounded-3 bg-white position-absolute shadow-sm';
            box.style.zIndex = 10000;
            box.style.left   = '0';
            box.style.right  = '0';
            box.style.top    = '100%';
            box.style.display = 'none';

            const parent = heatEl.parentElement || row;
            if (!parent.style.position) {
                parent.style.position = 'relative';
            }
            parent.appendChild(box);
        }
        return box;
    }

    // wire one row: autocomplete + fetch + calc
    function wireRow(row) {
        if (!row || row.dataset.wired === '1') return;
        row.dataset.wired = '1';

        const heatEl = findHeatEl(row);
        const pcsEl  = findQtyPcsEl(row);
        const kgEl   = findQtyKgEl(row);
        const suggestBox = heatEl ? ensureSuggestBox(row, heatEl) : null;

        if (heatEl && suggestBox) {
            const doSuggest = debounce(async () => {
                const q = heatEl.value.trim();
                if (!q) {
                    suggestBox.style.display = 'none';
                    suggestBox.innerHTML     = '';
                    return;
                }

                try {
                    const url = new URL(heatApi, window.location.origin);
                    url.searchParams.set('prefix', q);

                    const res = await fetch(url.toString(), {
                        credentials: 'same-origin',
                        headers: { 'Accept': 'application/json' }
                    });

                    if (!res.ok) return;
                    const j     = await res.json();
                    const items = j?.data ?? [];

                    if (!items.length) {
                        suggestBox.style.display = 'none';
                        suggestBox.innerHTML     = '';
                        return;
                    }

                    let html = '<div class="list-group list-group-flush">';
                    items.forEach(it => {
                        html += `
                            <button type="button"
                                class="list-group-item list-group-item-action py-2 small"
                                data-heat="${it.heat_number ?? ''}"
                                data-item="${it.item_code ?? ''}"
                                data-batch="${it.batch_code ?? ''}">
                                <div class="fw-semibold">
                                    ${it.heat_number ?? ''} <span class="text-muted">/ ${it.item_code ?? ''}</span>
                                </div>
                                <div class="text-muted small">${it.item_name ?? ''}</div>
                            </button>`;
                    });
                    html += '</div>';

                    suggestBox.innerHTML     = html;
                    suggestBox.style.display = 'block';

                    suggestBox.querySelectorAll('button').forEach(btn => {
                        btn.addEventListener('click', async function () {
                            const h     = this.getAttribute('data-heat');
                            const item  = this.getAttribute('data-item');
                            const batch = this.getAttribute('data-batch');

                            heatEl.value = h || heatEl.value;

                            const itemCodeEl = row.querySelector('.item-code');
                            if (itemCodeEl) itemCodeEl.value = item || '';

                            if (batch) heatEl.dataset.batchCode = batch;

                            suggestBox.style.display = 'none';

                            const info = await fetchItemInfoByHeat(h);
                            if (info) populateInfoToRow(row, info);
                        });
                    });

                } catch (err) {
                    console.error('heat suggestion error', err);
                }
            }, 200);

            heatEl.addEventListener('input', doSuggest);
            heatEl.addEventListener('focus', doSuggest);

            heatEl.addEventListener('change', debounce(async function () {
                const val = heatEl.value.trim();

                if (!val) {
                    const itemCodeEl = row.querySelector('.item-code');
                    const itemNameEl = row.querySelector('.item-name');

                    if (itemCodeEl) itemCodeEl.value = '';
                    if (itemNameEl) itemNameEl.value = '';

                    const hid = row.querySelector('.item-weight');
                    if (hid) hid.value = '';

                    row.dataset.weight = 0;
                    calcKgForRow(row);
                    return;
                }

                const info = await fetchItemInfoByHeat(val);
                if (info) populateInfoToRow(row, info);
            }, 200));
        }

        if (pcsEl) pcsEl.addEventListener('input', () => calcKgForRow(row));
        if (kgEl)  kgEl.addEventListener('input', () => { /* allow manual override if needed */ });
    }

    // initial wiring for existing rows
    document.querySelectorAll('#lines .line-row').forEach(wireRow);

    // handle add/remove row buttons
    document.addEventListener('click', function (e) {
        if (e.target.matches('.add-line')) {
            const row = e.target.closest('.line-row');
            if (!row) return;

            const clone = row.cloneNode(true);

            // reset values in clone
            clone.querySelectorAll('input,select,textarea').forEach(el => {
                if (!el) return;

                if (el.type === 'hidden') {
                    el.value = '';
                } else if (el.tagName === 'SELECT') {
                    el.selectedIndex = 0;
                } else {
                    el.value = '';
                }

                if (el.dataset && el.dataset.batchCode) {
                    delete el.dataset.batchCode;
                }
            });

            document.getElementById('lines').appendChild(clone);
            reindexAllRows();
            wireRow(clone);
        }

        if (e.target.matches('.remove-line')) {
            const row       = e.target.closest('.line-row');
            const container = document.getElementById('lines');

            if (!row || !container) return;

            const rows = container.querySelectorAll('.line-row');

            if (rows.length <= 1) {
                // reset fields instead of removing last row
                row.querySelectorAll('input,select,textarea').forEach(el => {
                    if (!el) return;

                    if (el.tagName === 'INPUT') {
                        el.value = (el.type === 'number') ? null : '';
                    } else if (el.tagName === 'SELECT') {
                        el.selectedIndex = 0;
                    }
                });

                const hid   = row.querySelector('input.batch-code-hidden');
                const badge = row.querySelector('.badge-batch-code');

                if (hid)   hid.value = '';
                if (badge) badge.remove();

                return;
            }

            row.remove();
            reindexAllRows();
        }
    });

    // reindex helper (keeps form names sequential)
    function reindexAllRows() {
        const rows = Array.from(document.querySelectorAll('#lines .line-row'));

        rows.forEach((row, idx) => {
            row.querySelectorAll('[name]').forEach(el => {
                el.name = el.name.replace(/lines\[\d+\]/, `lines[${idx}]`);
            });

            const hid    = row.querySelector('input.batch-code-hidden');
            const weight = row.querySelector('.item-weight');

            if (hid)    hid.name    = `lines[${idx}][batch_code]`;
            if (weight) weight.name = `lines[${idx}][weight_per_pc]`;
        });
    }

    // before submit: remove empty rows and reindex
    const form = document.getElementById('defectForm');
    if (form) {
        form.addEventListener('submit', function (ev) {
            const rows   = Array.from(document.querySelectorAll('#lines .line-row'));
            const filled = rows.filter(row => {
                const heat = (findHeatEl(row) && findHeatEl(row).value) || '';
                const item = (row.querySelector('.item-code') && row.querySelector('.item-code').value) || '';
                const pcs  = parseFloat((findQtyPcsEl(row) && findQtyPcsEl(row).value) || 0) || 0;

                return heat.trim() !== '' || item.trim() !== '' || pcs > 0;
            });

            if (filled.length === 0) {
                if (!confirm('Tidak ada baris terisi. Tetap ingin menyimpan draft kosong?')) {
                    ev.preventDefault();
                    return;
                }
            }

            rows.forEach(r => {
                const heat = (findHeatEl(r) && findHeatEl(r).value) || '';
                const item = (r.querySelector('.item-code') && r.querySelector('.item-code').value) || '';
                const pcs  = parseFloat((findQtyPcsEl(r) && findQtyPcsEl(r).value) || 0) || 0;

                if (!(heat.trim() !== '' || item.trim() !== '' || pcs > 0)) {
                    r.remove();
                }
            });

            reindexAllRows();
        });
    }

}); // DOMContentLoaded
</script>

{{-- JS tambahan untuk load kategori per departemen --}}
@include('defects._line_category_js')
@endpush
