@extends('layouts.app')
@section('content')

@php
    // support both variable names: $batches passed separately, or use relation
    $batches = $batches ?? $importSession->batches ?? collect();
    $displayDate = $importSession->date ?? $importSession->tanggal ?? null;
@endphp

<h4 class="fw-bold mb-3">📄 Detail Import — {{ optional($importSession->date)->format('Y-m-d') }}</h4>

<div class="card shadow-sm mb-3">
  <div class="card-body">

    {{-- Header meta --}}
    <div class="mb-3">
      <p><strong>Departemen:</strong>
        {{ $importSession->department->name ?? $importSession->departemen ?? '-' }}
      </p>

      <p><strong>Dibuat oleh:</strong>
        {{ $importSession->creator->name ?? $importSession->pembuat ?? ( $importSession->created_by ?? '-' ) }}
      </p>

      <p><strong>Tanggal Import:</strong>
        {{ optional($importSession->date)->format('Y-m-d') }}
      </p>

      <div class="mt-2 d-flex gap-2">
        {{-- Export buttons (implement routes in controller) --}}
        <a href="{{ route('imports.export', ['importSession' => $importSession->id, 'format' => 'xlsx']) }}"
           class="btn btn-sm btn-outline-success">
          Export XLSX
        </a>

        <a href="{{ route('imports.export', ['importSession' => $importSession->id, 'format' => 'pdf']) }}"
           class="btn btn-sm btn-outline-secondary" target="_blank">
          Export PDF
        </a>

        <a href="{{ route('imports.edit', $importSession->id) }}" class="btn btn-sm btn-warning ms-auto">
          Edit Import
        </a>
      </div>
    </div>

    {{-- Table --}}
    <div class="table-responsive">
      <table class="table table-bordered table-sm table-striped" id="import-detail-table">
        <thead class="table-light">
          <tr>
            <th style="width:48px">No</th>
            <th>Heat Number</th>
            <th>Kode Barang</th>
            <th>Nama Barang</th>
            <th>AISI</th>
            <th>Size</th>
            <th>Line</th>
            <th>Cust</th>
            <th class="text-end">Berat / PC</th>
            <th class="text-end">Batch Qty</th>
          </tr>
        </thead>

        <tbody>
          {{-- If many rows, render first chunk and collapse the rest for UX --}}
          @php
            $total = $batches->count();
            $preview = 20;
          @endphp

          @if($total <= $preview)
            @foreach($batches as $i => $b)
            <tr data-heat="{{ $b->heat_number }}">
              <td>{{ $i + 1 }}</td>
              <td class="heat-cell">{{ $b->heat_number }}</td>
              <td>{{ $b->item_code }}</td>
              <td>{{ $b->item_name }}</td>
              <td>{{ $b->aisi ?? '-' }}</td>
              <td>{{ $b->size ?? '-' }}</td>
              <td>{{ $b->line ?? '-' }}</td>
              <td>{{ $b->cust_name ?? '-' }}</td>
              <td class="text-end">{{ number_format($b->weight_per_pc ?? 0, 3) }}</td>
              <td class="text-end">{{ $b->batch_qty }}</td>
            </tr>
            @endforeach
          @else
            {{-- first chunk --}}
            @foreach($batches->slice(0, $preview) as $i => $b)
            <tr data-heat="{{ $b->heat_number }}">
              <td>{{ $i + 1 }}</td>
              <td class="heat-cell">{{ $b->heat_number }}</td>
              <td>{{ $b->item_code }}</td>
              <td>{{ $b->item_name }}</td>
              <td>{{ $b->aisi ?? '-' }}</td>
              <td>{{ $b->size ?? '-' }}</td>
              <td>{{ $b->line ?? '-' }}</td>
              <td>{{ $b->cust_name ?? '-' }}</td>
              <td class="text-end">{{ number_format($b->weight_per_pc ?? 0, 3) }}</td>
              <td class="text-end">{{ $b->batch_qty }}</td>
            </tr>
            @endforeach

            {{-- rest inside collapse --}}
            <tbody id="import-more-rows" class="collapse">
              @foreach($batches->slice($preview) as $j => $b)
              <tr data-heat="{{ $b->heat_number }}">
                <td>{{ $preview + $j + 1 }}</td>
                <td class="heat-cell">{{ $b->heat_number }}</td>
                <td>{{ $b->item_code }}</td>
                <td>{{ $b->item_name }}</td>
                <td>{{ $b->aisi ?? '-' }}</td>
                <td>{{ $b->size ?? '-' }}</td>
                <td>{{ $b->line ?? '-' }}</td>
                <td>{{ $b->cust_name ?? '-' }}</td>
                <td class="text-end">{{ number_format($b->weight_per_pc ?? 0, 3) }}</td>
                <td class="text-end">{{ $b->batch_qty }}</td>
              </tr>
              @endforeach
            </tbody>

            <tfoot>
              <tr>
                <td colspan="10" class="py-2">
                  <button class="btn btn-sm btn-link" type="button" data-bs-toggle="collapse" data-bs-target="#import-more-rows" aria-expanded="false" aria-controls="import-more-rows">
                    Tampilkan selengkapnya ({{ $total - $preview }} baris lagi)
                  </button>
                </td>
              </tr>
            </tfoot>
          @endif
        </tbody>
      </table>
    </div>

  </div>
</div>

{{-- styles for duplicate highlight --}}
@push('styles')
<style>
  /* gentle highlight for duplicate heats */
  .heat-duplicate {
    background-color: #ffe6e6 !important;
  }
  /* small badge on top-right to show dup count (optional) */
  .heat-dup-badge {
    font-size: 0.75rem;
    padding: .18rem .4rem;
    vertical-align: middle;
  }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
  // highlight duplicate heat numbers in the table
  (function highlightDupes() {
    const rows = Array.from(document.querySelectorAll('#import-detail-table tbody tr'));
    const map = {};
    rows.forEach(row => {
      const heat = (row.dataset.heat || '').trim();
      if (!heat) return;
      map[heat] = (map[heat] || []);
      map[heat].push(row);
    });

    Object.keys(map).forEach(heat => {
      const group = map[heat];
      if (group.length > 1) {
        // mark duplicates visually
        group.forEach(r => r.classList.add('heat-duplicate'));
        // optionally append badge to first cell of first occurrence
        const first = group[0];
        const badge = document.createElement('span');
        badge.className = 'badge bg-danger text-white ms-2 heat-dup-badge';
        badge.textContent = 'dup ×' + group.length;
        const heatCell = first.querySelector('.heat-cell');
        if (heatCell) heatCell.appendChild(badge);
      }
    });
  })();
});
</script>
@endpush

@endsection
