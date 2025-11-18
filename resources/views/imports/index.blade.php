@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">📦 Riwayat Import Batch</h4>
  <a href="{{ route('imports.create') }}" class="btn btn-primary btn-sm">Import Baru</a>
</div>

<div class="card shadow-sm">
  <div class="card-body p-0">
    @if($sessions->count())
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>Tanggal</th>
              <th>Departemen</th>
              <th>Oleh</th>
              <th class="text-center">Total Batch</th>
              <th class="text-end" style="width:260px">Aksi</th>
            </tr>
          </thead>

          <tbody>
            @foreach($sessions as $s)
              <tr>
                <td>
                  @if($s->date instanceof \Illuminate\Support\Carbon)
                    {{ $s->date->format('Y-m-d') }}
                  @else
                    {{ \Illuminate\Support\Carbon::parse($s->date ?? now())->format('Y-m-d') }}
                  @endif
                </td>

                <td>{{ optional($s->department)->name ?? '—' }}</td>

                <td>
                  {{-- Tampilkan nama pembuat jika ada relasi 'creator' atau tampilkan id --}}
                  {{ optional($s->creator)->name ?? optional($s->createdBy)->name ?? ($s->created_by ?? '—') }}
                </td>

                <td class="text-center">
                  <span class="badge bg-primary">{{ $s->batches_count ?? $s->batches()->count() }}</span>
                </td>

                <td class="text-end">
                  <a href="{{ route('imports.show', $s->id) }}" class="btn btn-sm btn-info text-white">Lihat</a>

                  {{-- Hanya role tertentu boleh edit/hapus --}}
                  @if(in_array(optional(auth()->user())->role, ['kabag_qc', 'direktur']))
                    <a href="{{ route('imports.edit', $s->id) }}" class="btn btn-sm btn-warning">Edit</a>

                    <form action="{{ route('imports.destroy', $s->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus sesi import ini?');">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-sm btn-danger">Hapus</button>
                    </form>
                  @endif
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>

      <div class="card-footer bg-white">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            Menampilkan {{ $sessions->firstItem() }} - {{ $sessions->lastItem() }} dari {{ $sessions->total() }} sesi
          </div>
          <div>
            {{ $sessions->links() }}
          </div>
        </div>
      </div>
    @else
      <div class="p-4 text-center">
        <p class="mb-2">Belum ada sesi import.</p>
        <a href="{{ route('imports.create') }}" class="btn btn-outline-primary btn-sm">Buat Import Baru</a>
      </div>
    @endif
  </div>
</div>
@endsection
