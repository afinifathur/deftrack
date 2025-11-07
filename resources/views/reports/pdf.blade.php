{{-- resources/views/reports/pdf.blade.php --}}
@php
  $primary = '#007bff';
  $accent  = '#17a2b8';
@endphp
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 10mm 10mm 12mm 10mm; }
    body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #222; }
    .header { display:flex; align-items:center; gap:10px; margin-bottom:8px; }
    .brand { font-weight:800; color: {{ $primary }}; letter-spacing:.06em; }
    .tagline { color:#555; font-size:10px; }
    .meta { font-size:10px; color:#666; margin-bottom:8px; }
    table { width:100%; border-collapse:collapse; }
    th, td { border:1px solid #ddd; padding:6px 8px; }
    th { background:#f2f6ff; font-weight:700; }
    .right { text-align:right; }
    .nowrap { white-space:nowrap; }
    .small { font-size:10px; color:#666; }
  </style>
</head>
<body>
  <div class="header">
    <img src="{{ public_path('img/logo-deftrack.png') }}" alt="DEFTRACK" style="height:28px;">
    <div>
      <div class="brand">DEFTRACK</div>
      <div class="tagline">Data-Driven Quality for Manufacturing.</div>
    </div>
  </div>
  <div class="meta">
    Periode: <strong>{{ $meta['from'] }}</strong> s/d <strong>{{ $meta['to'] }}</strong> &nbsp;|&nbsp;
    Dibuat: {{ $meta['generated_at'] }} WIB
  </div>

  <table>
    <thead>
      <tr>
        <th class="nowrap">Tanggal</th>
        <th>Departemen</th>
        <th>Heat</th>
        <th>Item Code</th>
        <th>Defect</th>
        <th class="right nowrap">Qty PCS</th>
        <th class="right nowrap">Qty KG</th>
      </tr>
    </thead>
    <tbody>
    @forelse($rows as $r)
      <tr>
        <td class="nowrap">{{ $r->date }}</td>
        <td>{{ $r->department }}</td>
        <td class="nowrap">{{ $r->heat_number }}</td>
        <td class="nowrap">{{ $r->item_code }}</td>
        <td>{{ $r->defect_type }}</td>
        <td class="right">{{ number_format($r->qty_pcs) }}</td>
        <td class="right">{{ number_format($r->qty_kg, 3) }}</td>
      </tr>
    @empty
      <tr><td colspan="7" class="small">Tidak ada data untuk periode ini.</td></tr>
    @endforelse
    </tbody>
  </table>

  <div class="small" style="margin-top:6px;">
    Catatan: PDF dibatasi maksimal 1 bulan untuk menjaga stabilitas render.
  </div>
</body>
</html>
