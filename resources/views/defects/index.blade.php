@extends('layouts.app')

@section('content')
@php
    /** @var \App\Models\User|null $user */
    $user = auth()->user();
    $userRole = $user?->role;
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Defects</h4>
    <a href="{{ route('defects.create') }}" class="btn btn-success btn-sm">Tambah</a>
</div>

{{-- Search + Filter (GET supaya URL bisa dishare & paginasi aman) --}}
<form method="GET" action="{{ route('defects.index') }}" class="row g-2 mb-3 align-items-center">
    <div class="col-auto">
        <input
            type="search"
            name="q"
            id="q"
            value="{{ request('q') }}"
            class="form-control form-control-sm"
            placeholder="Cari heat / kode batch / item..."
            autocomplete="off"
        >
    </div>

    <div class="col-auto">
        <select name="department_id" id="filter_dept" class="form-select form-select-sm">
            <option value="">Semua Departemen</option>
            @foreach($departments as $dept)
                <option
                    value="{{ $dept->id }}"
                    @selected((string) request('department_id') === (string) $dept->id)
                >
                    {{ $dept->name }}
                </option>
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
                @php
                    $date = $defect->date;
                    $statusRaw = $defect->status ?? '';
                    $statusUpper = strtoupper($statusRaw);
                    $firstLine = $defect->defect_lines->first();
                    $batch = $firstLine?->batch;

                    // Optional: mapping warna badge status
                    $statusClass = match ($statusRaw) {
                        'draft'     => 'bg-secondary',
                        'submitted' => 'bg-info',
                        'approved'  => 'bg-success',
                        'rejected'  => 'bg-danger',
                        default     => 'bg-secondary',
                    };
                @endphp

                <tr>
                    {{-- Tanggal (support Carbon atau string biasa) --}}
                    <td>
                        @if($date instanceof \Carbon\Carbon)
                            {{ $date->format('Y-m-d') }}
                        @else
                            {{ $date ?? '-' }}
                        @endif
                    </td>

                    {{-- Departemen --}}
                    <td>{{ $defect->department?->name ?? '-' }}</td>

                    {{-- Status --}}
                    <td>
                        <span class="badge {{ $statusClass }} text-uppercase">
                            {{ $statusUpper ?: '-' }}
                        </span>
                    </td>

                    {{-- Kode batch dari line pertama (jika ada) --}}
                    <td>
                        {{ $batch?->batch_code ?? '-' }}
                    </td>

                    {{-- Aksi --}}
                    <td>
                        {{-- Lihat (semua user login) --}}
                        <a href="{{ route('defects.show', $defect->id) }}" class="btn btn-sm btn-info">
                            Lihat
                        </a>

                        {{-- Edit: role tertentu --}}
                        @if(in_array($userRole, ['admin_qc', 'kabag_qc', 'direktur', 'mr'], true))
                            <a href="{{ route('defects.edit', $defect->id) }}" class="btn btn-sm btn-warning">
                                Edit
                            </a>
                        @endif

                        {{-- Submit: hanya status draft & role admin_qc --}}
                        @if($statusRaw === 'draft' && $userRole === 'admin_qc')
                            <form method="POST"
                                  action="{{ route('defects.submit', $defect->id) }}"
                                  class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-info">
                                    Submit
                                </button>
                            </form>
                        @endif

                        {{-- Approve / Reject: hanya kabag_qc & status submitted --}}
                        @if($statusRaw === 'submitted' && $userRole === 'kabag_qc')
                            <form method="POST"
                                  action="{{ route('approvals.approve', $defect->id) }}"
                                  class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success">
                                    Approve
                                </button>
                            </form>

                            <form method="POST"
                                  action="{{ route('approvals.reject', $defect->id) }}"
                                  class="d-inline">
                                @csrf
                                <input type="hidden" name="reason" value="Tidak sesuai data">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    Reject
                                </button>
                            </form>
                        @endif

                        {{-- Delete (soft delete): role tertentu --}}
                        @if(in_array($userRole, ['kabag_qc', 'direktur', 'mr'], true))
                            <form method="POST"
                                  action="{{ route('defects.destroy', $defect->id) }}"
                                  class="d-inline"
                                  onsubmit="return confirm('Pindahkan defect ke recycle?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    Delete
                                </button>
                            </form>
                        @endif

                        {{-- Alasan reject (jika ada) --}}
                        @if($statusRaw === 'rejected' && !empty($defect->rejected_reason))
                            <div class="mt-1 text-danger small">
                                Alasan: {{ $defect->rejected_reason }}
                            </div>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center text-muted">
                        Tidak ada data defects.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Pagination: tetap membawa query string pencarian/filter --}}
<div class="d-flex justify-content-end">
    {{ $defects->appends(request()->query())->links() }}
</div>
@endsection

@push('scripts')
<script>
    (function () {
        const q = document.getElementById('q');
        if (!q) return;

        q.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                q.closest('form').submit();
            }
        });
    })();
</script>
@endpush
