<!-- resources/views/defects/_line_row.blade.php -->
<div class="card mb-3 line-row">
  <div class="card-body">
    <div class="d-flex flex-wrap align-items-center gap-2">
      <div style="flex:0 0 140px">
        <label class="small">Heat No.</label>
        <input type="text" name="lines[][heat]" class="form-control form-control-sm heat-number" placeholder="cth: H240901" autocomplete="off">
      </div>

      <div style="flex:0 0 120px">
        <label class="small">Item Code</label>
        <input type="text" name="lines[][item_code]" class="form-control form-control-sm item-code" readonly>
      </div>

      <div style="flex:1 1 260px">
        <label class="small">Item Name</label>
        <input type="text" name="lines[][item_name]" class="form-control form-control-sm item-name" readonly>
      </div>

      <div style="flex:0 0 70px">
        <label class="small">AISI</label>
        <input type="text" name="lines[][aisi]" class="form-control form-control-sm aisi" readonly>
      </div>

      <div style="flex:0 0 80px">
        <label class="small">Size</label>
        <input type="text" name="lines[][size]" class="form-control form-control-sm size" readonly>
      </div>

      <div style="flex:0 0 120px">
        <label class="small">Line</label>
        <input type="text" name="lines[][line]" class="form-control form-control-sm line" readonly>
      </div>
    </div>

    <div class="d-flex align-items-center gap-2 mt-3">
      <div style="flex:0 0 140px">
        <label class="small">Cust</label>
        <input type="text" name="lines[][cust_name]" class="form-control form-control-sm cust_name" readonly>
      </div>

      <div style="flex:0 0 110px">
        <label class="small">Qty PCS</label>
        <input type="number" name="lines[][qty_pcs]" class="form-control form-control-sm qty-pcs" value="0" min="0" step="1">
      </div>

      <div style="flex:0 0 140px">
        <label class="small">Qty KG</label>
        <input type="text" name="lines[][qty_kg]" class="form-control form-control-sm qty-kg" value="0.000" readonly style="font-weight:600;">
      </div>

      <div style="flex:1 1 260px">
        <label class="small">Kategori</label>
        <select name="lines[][defect_category_id]" class="form-control form-control-sm category-select">
          <option value="">— Pilih Kategori —</option>
          @foreach($categories as $c)
            <option value="{{ $c->id }}">{{ $c->name }}</option>
          @endforeach
        </select>
      </div>

      <div class="ml-auto">
        <button type="button" class="btn btn-sm btn-outline-secondary add-row-btn">+ Tambah</button>
        <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn">Hapus</button>
      </div>
    </div>

    <!-- hidden weight for calc (populated by JS when heat/item info fetched) -->
    <input type="hidden" class="item-weight" name="lines[][weight_per_pc]" value="0">
  </div>
</div>
