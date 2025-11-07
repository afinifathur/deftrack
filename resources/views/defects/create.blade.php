{{-- resources/views/defects/create.blade.php --}}
@extends('layouts.app')

@section('content')
<h4 class="fw-bold mb-3">Input Kerusakan (Parent–Child)</h4>

<div class="card mb-3">
  <div class="card-body">
    <form method="POST" action="{{ route('defects.store') }}" id="defectForm">
      @csrf
      <div class="row g-3 mb-2">
        <div class="col-md-3">
          <label class="form-label">Tanggal</label>
          <input type="date" name="date" class="form-control" required value="{{ now()->toDateString() }}">
        </div>
        <div class="col-md-3">
          <label class="form-label">Departemen</label>
          <select name="department_id" class="form-select" required>
            @foreach($departments as $d)
              <option value="{{ $d->id }}">{{ $d->name }}</option>
            @endforeach
          </select>
        </div>
      </div>

      <div id="lines" class="vstack gap-2">
        @for($i=0; $i<5; $i++)
        <div class="line-row p-3 rounded-3 border bg-white">
          <div class="row g-3 align-items-end">
            <div class="col-md-2">
              <label class="form-label">Heat No.</label>
              <input class="form-control heat" name="lines[{{ $i }}][heat_number]" placeholder="cth: H240901" required>
            </div>
            <div class="col-md-2">
              <label class="form-label">Item Code</label>
              <input class="form-control" name="lines[{{ $i }}][item_code]" placeholder="cth: FLG-2IN-150" required>
            </div>
            <div class="col-md-3">
              <label class="form-label">Kategori</label>
              <select class="form-select category" name="lines[{{ $i }}][defect_type_id]" required>
                <option value="">— Pilih Kategori —</option>
                @foreach($types->where('parent_id', null) as $t)
                  <option value="{{ $t->id }}">{{ $t->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label">Subkategori</label>
              <select class="form-select subcategory" name="lines[{{ $i }}][subtype_id]">
                <option value="">— Pilih Subkategori —</option>
              </select>
            </div>
            <div class="col-md-1">
              <label class="form-label">Qty PCS</label>
              <input class="form-control" type="number" min="0" name="lines[{{ $i }}][qty_pcs]" placeholder="0">
            </div>
            <div class="col-md-1">
              <label class="form-label">Qty KG</label>
              <input class="form-control" type="number" min="0" step="0.001" name="lines[{{ $i }}][qty_kg]" placeholder="0.000">
            </div>
          </div>
          <div class="d-flex justify-content-end mt-2">
            <button type="button" class="btn btn-outline-secondary btn-sm add-line">+ Tambah Baris</button>
          </div>
        </div>
        @endfor
      </div>

      <div class="d-flex gap-2 mt-3">
        <button class="btn btn-primary px-4">Simpan Draft</button>
        <a href="{{ route('defects.index') }}" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function(){
  // Data kategori & subkategori dari controller
  const typeTree = @json($typeTree ?? []);
  function safeChildren(parentId){
    if(!Array.isArray(typeTree)) return [];
    const node = typeTree.find(t => String(t.id) === String(parentId));
    return node && Array.isArray(node.children) ? node.children : [];
  }

  // Suggest dropdown untuk heat
  function ensureSuggestBox(rowEl){
    const heatInput = rowEl.querySelector('.heat');
    if(!heatInput) return null;
    let box = rowEl.querySelector('.heat-suggest');
    if(!box){
      box = document.createElement('div');
      box.className = 'heat-suggest border rounded-3 bg-white position-absolute shadow-sm';
      box.style.zIndex = 1000;
      box.style.display = 'none';
      heatInput.parentElement.style.position = 'relative';
      heatInput.parentElement.appendChild(box);
    }
    return box;
  }
  function renderSuggestions(box, items, onPick){
    if(!box) return;
    if(!items || !items.length){ box.style.display='none'; box.innerHTML=''; return; }
    let html = '<div class="list-group list-group-flush">';
    items.forEach(it=>{
      html += `
        <button type="button" class="list-group-item list-group-item-action py-2 small"
                data-h="${it.heat_number}" data-i="${it.item_code}">
          <div class="fw-semibold">${it.heat_number} <span class="text-muted">/ ${it.item_code}</span></div>
          <div class="text-muted">${it.item_name ?? ''}</div>
        </button>`;
    });
    html += '</div>';
    box.innerHTML = html;
    box.style.display = 'block';
    box.querySelectorAll('button').forEach(btn=>{
      btn.addEventListener('click', function(){
        onPick(this.getAttribute('data-h'), this.getAttribute('data-i'));
      });
    });
  }
  const debounce = window.deftrackDebounce || function(fn, d=275){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a),d); } };

  function wireRow(rowEl){
    if(!rowEl) return;
    const heatInput = rowEl.querySelector('.heat');
    const itemInput = rowEl.querySelector('input[name*="[item_code]"]');
    const cat = rowEl.querySelector('.category');
    const sub = rowEl.querySelector('.subcategory');
    const suggest = ensureSuggestBox(rowEl);

    // dependent select
    if(cat && sub){
      cat.addEventListener('change', function(){
        const children = safeChildren(this.value);
        sub.innerHTML = '<option value="">— Pilih Subkategori —</option>';
        children.forEach(c=>{
          const opt = document.createElement('option');
          opt.value = c.id; opt.textContent = c.name;
          sub.appendChild(opt);
        });
      }, { passive: true });
    }

    // autocomplete heat
    if(heatInput){
      const fetchSuggest = debounce(async function(){
        const q = heatInput.value.trim();
        if(q.length < 1){ renderSuggestions(suggest, [], ()=>{}); return; }
        try{
          const res = await fetch(`{{ route('api.heat') }}?prefix=${encodeURIComponent(q)}`, { headers:{ 'Accept':'application/json' } });
          const js  = await res.json();
          const items = (js && js.data) ? js.data : [];
          renderSuggestions(suggest, items, function(heat, item){
            heatInput.value = heat;
            if(itemInput) itemInput.value = item;
            renderSuggestions(suggest, [], ()=>{});
          });
        }catch(e){
          renderSuggestions(suggest, [], ()=>{});
        }
      }, 300);
      heatInput.addEventListener('input', fetchSuggest);
      heatInput.addEventListener('focus', fetchSuggest);
      document.addEventListener('click', function(ev){
        if(suggest && !suggest.contains(ev.target) && ev.target !== heatInput){ suggest.style.display='none'; }
      });
    }

    // tombol tambah baris
    const addBtn = rowEl.querySelector('.add-line');
    if(addBtn){
      addBtn.addEventListener('click', function(){
        const lines = document.getElementById('lines');
        if(!lines) return;
        const idx = lines.querySelectorAll('.line-row').length;
        const tpl = rowEl.cloneNode(true);

        // bersihkan nilai & ganti index (regex global)
        tpl.querySelectorAll('input,select').forEach(el=>{
          // reset
          if(el.tagName === 'SELECT'){
            if(el.classList.contains('subcategory')){
              el.innerHTML = '<option value="">— Pilih Subkategori —</option>';
            }
            el.value = '';
          } else {
            el.value = '';
          }
          // ganti index kalau name ada
          if (el.name) {
            el.name = el.name.replace(/\[\d+\]/g, '['+idx+']');
          }
        });

        // hapus suggest box lama pada clone
        const oldBox = tpl.querySelector('.heat-suggest');
        if(oldBox) oldBox.remove();

        lines.appendChild(tpl);
        wireRow(tpl);
      });
    }
  }

  // wire semua row awal (pastikan elemen ada)
  document.querySelectorAll('.line-row').forEach(wireRow);
});
</script>
@endpush
