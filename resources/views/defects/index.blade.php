@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Defects</h4>
  <a href="{{ route('defects.create') }}" class="btn btn-success btn-sm">Tambah</a>
</div>

{{-- Search + Filter: gunakan GET supaya URL dapat dibagikan / paginasi bekerja --}}
<form method="GET" action="{{ route('defects.index') }}" class="row g-2 mb-3 align-items-center">
  <div class="col-auto">
    <input
      name="q"
      id="q"
      type="search"
      value="{{ request('q') }}"
      class="form-control form-control-sm"
      placeholder="Cari heat / kode batch / item..."
      autocomplete="off">
  </div>

  <div class="col-auto">
    <select name="department_id" id="filter_dept" class="form-select form-select-sm">
      <option value="">Semua Departemen</option>
      @foreach($departments as $dept)
        <option value="{{ $dept->id }}" @selected((string)request('department_id') === (string)$dept->id)>{{ $dept->name }}</option>
      @endforeach
    </select>
  </div>

  <div class="col-auto">
    <button type="submit" class="btn btn-primary btn-sm">Cari</button>
    <a href="{{ route('defects.index') }}" class="btn btn-outline-secondary btn-sm ms-1">Reset</a>
  </div>
</form>

<div class="table-responsive">
  <table class="table table-sm table-hover align-middle">
    <thead class="table-light">
      <tr>
        <th style="width:120px">Tanggal</th>
        <th>Departemen</th>
        <th style="width:120px">Status</th>
        <th>Kode Batch</th>
        <th style="width:240px">Aksi</th>
      </tr>
    </thead>
    <tbody>
      @forelse($defects as $defect)
        <tr>
          {{-- tanggal: dukung Carbon atau string --}}
          <td>{{ optional($defect->date) instanceof \Carbon\Carbon ? $defect->date->format('Y-m-d') : ( $defect->date ?? '-' ) }}</td>

          <td>{{ optional($defect->department)->name ?? '-' }}</td>

          <td>
            @php $status = strtoupper($defect->status ?? ''); @endphp
            <span class="badge bg-secondary text-uppercase">{{ $status ?: '-' }}</span>
          </td>

          {{-- ambil kode batch dari baris defect pertama jika ada --}}
          <td>
            {{ optional($defect->defect_lines->first()->batch)->batch_code ?? '-' }}
          </td>

          <td>
            {{-- Lihat (semua user yang login dapat melihat) --}}
            <a href="{{ route('defects.show', $defect->id) }}" class="btn btn-sm btn-info">Lihat</a>

            {{-- Edit: hanya role tertentu --}}
            @if(in_array(auth()->user()?->role, ['admin_qc','kabag_qc','direktur','mr']))
              <a href="{{ route('defects.edit', $defect->id) }}" class="btn btn-sm btn-warning">Edit</a>
            @endif

            {{-- Submit (contoh: hanya tampil untuk draft & admin_qc) --}}
            @if($defect->status === 'draft' && auth()->user()?->role === 'admin_qc')
              <form method="POST" action="{{ route('defects.submit', $defect->id) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-info">Submit</button>
              </form>
            @endif

            {{-- Approve / Reject (contoh: hanya kabag_qc & status submitted) --}}
            @if($defect->status === 'submitted' && auth()->user()?->role === 'kabag_qc')
              <form method="POST" action="{{ route('approvals.approve', $defect->id) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-success">Approve</button>
              </form>

              <form method="POST" action="{{ route('approvals.reject', $defect->id) }}" class="d-inline">
                @csrf
                <input type="hidden" name="reason" value="Tidak sesuai data">
                <button type="submit" class="btn btn-sm btn-danger">Reject</button>
              </form>
            @endif

            {{-- Delete (soft delete) --}}
            @if(in_array(auth()->user()?->role, ['kabag_qc','direktur','mr']))
              <form action="{{ route('defects.destroy', $defect->id) }}" method="POST" class="d-inline"
                    onsubmit="return confirm('Pindahkan defect ke recycle?')">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            @endif

            {{-- Tampilkan alasan reject jika ada --}}
            @if($defect->status === 'rejected' && !empty($defect->rejected_reason))
              <div class="mt-1 text-danger small">Alasan: {{ $defect->rejected_reason }}</div>
            @endif
          </td>
        </tr>
      @empty
        <tr>
          <td colspan="5" class="text-center text-muted">Tidak ada data defects.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

{{-- Pagination: pertahankan query string --}}
<div class="d-flex justify-content-end">
  {{ $defects->appends(request()->query())->links() }}
</div>
@endsection

@push('scripts')
<script>
  // optional: submit form with Enter from the search input
  (function(){
    const q = document.getElementById('q');
    if(!q) return;
    q.addEventListener('keydown', function(e){
      if (e.key === 'Enter') {
        e.preventDefault();
        q.closest('form').submit();
      }
    });
  })();
</script>
@endpush
