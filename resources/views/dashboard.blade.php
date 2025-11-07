@extends('layouts.app')

@section('content')
<h4 class="fw-bold mb-3">Dashboard (6 bulan terakhir)</h4>
<div class="alert alert-info shadow-sm rounded-3">
  KPI % Defect (periode): <strong>{{ $kpi }}%</strong>
</div>

<div class="row g-3">
  <div class="col-md-6">
    <div class="card"><div class="card-body">
      <h6 class="card-title">PCS per Minggu</h6>
      <div class="chart-box-320"><canvas id="chartPcs"></canvas></div>
    </div></div>
  </div>
  <div class="col-md-6">
    <div class="card"><div class="card-body">
      <h6 class="card-title">KG per Minggu</h6>
      <div class="chart-box-320"><canvas id="chartKg"></canvas></div>
    </div></div>
  </div>
  <div class="col-md-6">
    <div class="card"><div class="card-body">
      <h6 class="card-title">Top Defect (Donut)</h6>
      <div class="chart-box-320"><canvas id="chartDonut"></canvas></div>
    </div></div>
  </div>
  <div class="col-md-6">
    <div class="card"><div class="card-body">
      <h6 class="card-title">Trend per Departemen (PCS)</h6>
      <div class="chart-box-360"><canvas id="chartDept"></canvas></div>
    </div></div>
  </div>
  <div class="col-12">
    <div class="card"><div class="card-body">
      <h6 class="card-title">% Defect per Minggu</h6>
      <div class="chart-box-280"><canvas id="chartKpi"></canvas></div>
    </div></div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  // Data dari controller
  const weekLabels   = @json($weekLabels ?? []);
  const pcsSeries    = @json($pcsSeries ?? []);
  const kgSeries     = @json($kgSeries ?? []);
  const donutLabels  = @json($donutLabels ?? []);
  const donutData    = @json($donutData ?? []);
  const deptSeries   = @json($deptSeries ?? []);
  const kpiWeekly    = @json($kpiWeekly ?? []);

  const optsLine = { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } } };

  window.makeDeftrackChart('chartPcs', {
    type:'line',
    data:{ labels: weekLabels, datasets:[{ label:'PCS', data: pcsSeries, tension:.25 }] },
    options: optsLine
  });

  window.makeDeftrackChart('chartKg', {
    type:'line',
    data:{ labels: weekLabels, datasets:[{ label:'KG', data: kgSeries, tension:.25 }] },
    options: optsLine
  });

  window.makeDeftrackChart('chartDonut', {
    type:'doughnut',
    data:{ labels: donutLabels, datasets:[{ data: donutData }] },
    options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom' } } }
  });

  window.makeDeftrackChart('chartDept', {
    type:'line',
    data:{ labels: weekLabels, datasets: (deptSeries || []).map(s => ({ label:s.label, data:s.data, tension:.25 })) },
    options:{ responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true } }, plugins:{ legend:{ position:'bottom' } } }
  });

  window.makeDeftrackChart('chartKpi', {
    type:'line',
    data:{ labels: weekLabels, datasets:[{ label:'% Defect', data: kpiWeekly, tension:.25 }] },
    options:{ responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true, ticks:{ callback:v=>v+'%' } } } }
  });
})();
</script>
@endpush
