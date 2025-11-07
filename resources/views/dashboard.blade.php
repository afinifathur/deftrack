@extends('layouts.app')

@section('content')
<h4 class="fw-bold mb-3">Dashboard (6 bulan terakhir)</h4>
<div class="alert alert-info shadow-sm rounded-3">
  KPI % Defect (periode): <strong>{{ $kpi }}%</strong>
</div>

<div class="row g-3">
  {{-- BARIS 1: PCS + Donut Kategori --}}
  <div class="col-lg-8">
    <div class="card"><div class="card-body">
      <h6 class="card-title">PCS per Minggu</h6>
      <div class="chart-box-320"><canvas id="chartPcs"></canvas></div>
    </div></div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100"><div class="card-body">
      <h6 class="card-title">Top Defect (Per Kategori)</h6>
      <div class="chart-box-320"><canvas id="chartDonutCat"></canvas></div>
    </div></div>
  </div>

  {{-- BARIS 2: KG + Donut Departemen --}}
  <div class="col-lg-8">
    <div class="card"><div class="card-body">
      <h6 class="card-title">KG per Minggu</h6>
      <div class="chart-box-320"><canvas id="chartKg"></canvas></div>
    </div></div>
  </div>
  <div class="col-lg-4">
    <div class="card h-100"><div class="card-body">
      <h6 class="card-title">Top Defect (Per Departemen)</h6>
      <div class="chart-box-320"><canvas id="chartDonutDept"></canvas></div>
    </div></div>
  </div>

  {{-- BARIS 3: Trend per Departemen --}}
  <div class="col-12">
    <div class="card"><div class="card-body">
      <h6 class="card-title">Trend per Departemen (PCS)</h6>
      <div class="chart-box-360"><canvas id="chartDept"></canvas></div>
    </div></div>
  </div>

  {{-- BARIS 4: % Defect per Minggu --}}
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
  // ===== Data dari controller =====
  const weekStarts       = @json($weekStarts ?? []);
  const weekNumbers      = @json($weekNumbers ?? []);
  const pcsSeries        = @json($pcsSeries ?? []);
  const kgSeries         = @json($kgSeries ?? []);
  const donutLabels      = @json($donutLabels ?? []);      
  const donutData        = @json($donutData ?? []);
  const deptDonutLabels  = @json($deptDonutLabels ?? []);  
  const deptDonutData    = @json($deptDonutData ?? []);
  const deptSeries       = @json($deptSeries ?? []);
  const kpiWeekly        = @json($kpiWeekly ?? []);

  // ===== Plugin label bulan =====
  const monthFmt = new Intl.DateTimeFormat('id-ID', { month: 'short' });
  const monthOf  = i => new Date(weekStarts[i] + 'T00:00:00').getMonth();
  const monthLbl = i => monthFmt.format(new Date(weekStarts[i] + 'T00:00:00'));
  const monthSegments = (() => {
    if (!Array.isArray(weekStarts) || weekStarts.length === 0) return [];
    const segs = []; let s = 0;
    for (let i = 1; i < weekStarts.length; i++) {
      if (monthOf(i) !== monthOf(i-1)) { segs.push({from:s,to:i-1,label:monthLbl(i-1)}); s=i; }
    }
    segs.push({from:s,to:weekStarts.length-1,label:monthLbl(weekStarts.length-1)});
    return segs;
  })();

  const monthBandPlugin = {
    id: 'monthBandPlugin',
    afterDraw(chart) {
      const {ctx, chartArea, scales} = chart;
      const x = scales.x; if (!x || monthSegments.length === 0) return;
      const step = (weekNumbers.length > 1) ? x.getPixelForValue(1)-x.getPixelForValue(0) : 0;
      const baseline = chartArea.bottom + 36;
      ctx.save(); ctx.textAlign='center'; ctx.textBaseline='middle'; ctx.fillStyle='#556'; ctx.font='12px Arial';
      monthSegments.forEach((seg, idx)=>{
        const cx1=x.getPixelForValue(seg.from), cx2=x.getPixelForValue(seg.to), center=(cx1+cx2)/2;
        ctx.fillText(seg.label, center, baseline);
        if(idx<monthSegments.length-1&&step>0){
          const boundary=cx2+step/2;
          ctx.strokeStyle='rgba(0,0,0,0.08)';
          ctx.beginPath();ctx.moveTo(boundary,chartArea.top);ctx.lineTo(boundary,chartArea.bottom);ctx.stroke();
        }
      });
      ctx.restore();
    }
  };

  // ===== Plugin teks di tengah Donut =====
  const centerTextPlugin = {
    id: 'centerTextPlugin',
    afterDraw(chart) {
      const {ctx, chartArea, chartArea:{width,height}, chartArea:{top,bottom,left,right}} = chart;
      const datasets = chart.data.datasets;
      if (!datasets || !datasets.length) return;
      const total = datasets[0].data.reduce((a,b)=>a+b,0);
      if (!total) return;
      const active = chart.getActiveElements();
      let label, percent;
      if (active.length) {
        const i = active[0].index;
        const val = datasets[0].data[i];
        label = chart.data.labels[i];
        percent = ((val / total) * 100).toFixed(1) + '%';
      } else {
        percent = '100%';
        label = 'Total';
      }
      const {x,y} = chart.getDatasetMeta(0).data[0];
      ctx.save();
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.font = 'bold 16px Arial';
      ctx.fillStyle = '#333';
      ctx.fillText(percent, x, y);
      ctx.font = '12px Arial';
      ctx.fillStyle = '#777';
      ctx.fillText(label, x, y + 18);
      ctx.restore();
    }
  };

  // ===== Opsi chart garis =====
  const commonLineOptions = {
    responsive:true, maintainAspectRatio:false,
    layout:{ padding:{ bottom:40 } },
    scales:{
      x:{ ticks:{ callback:(v,i)=>weekNumbers[i], maxRotation:0, minRotation:0 }, grid:{ drawOnChartArea:true }},
      y:{ beginAtZero:true }
    },
    plugins:{ legend:{ position:'top' } }
  };

  // ===== Render Charts =====
  window.makeDeftrackChart('chartPcs', {
    type:'line',
    data:{ labels:weekNumbers, datasets:[{ label:'PCS', data:pcsSeries, tension:.25, pointRadius:2 }] },
    options:commonLineOptions, plugins:[monthBandPlugin]
  });

  window.makeDeftrackChart('chartKg', {
    type:'line',
    data:{ labels:weekNumbers, datasets:[{ label:'KG', data:kgSeries, tension:.25, pointRadius:2 }] },
    options:commonLineOptions, plugins:[monthBandPlugin]
  });

  window.makeDeftrackChart('chartDonutCat', {
    type:'doughnut',
    data:{ labels:donutLabels, datasets:[{ data:donutData }] },
    options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom' } } },
    plugins:[centerTextPlugin]
  });

  window.makeDeftrackChart('chartDonutDept', {
    type:'doughnut',
    data:{ labels:deptDonutLabels, datasets:[{ data:deptDonutData }] },
    options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'bottom' } } },
    plugins:[centerTextPlugin]
  });

  window.makeDeftrackChart('chartDept', {
    type:'line',
    data:{ labels:weekNumbers, datasets:(deptSeries||[]).map(s=>({ label:s.label, data:s.data, tension:.25 })) },
    options:{ ...commonLineOptions, plugins:{ legend:{ position:'bottom' } } },
    plugins:[monthBandPlugin]
  });

  window.makeDeftrackChart('chartKpi', {
    type:'line',
    data:{ labels:weekNumbers, datasets:[{ label:'% Defect', data:kpiWeekly, tension:.25, pointRadius:2 }] },
    options:{ ...commonLineOptions, scales:{ ...commonLineOptions.scales, y:{ beginAtZero:true, ticks:{ callback:v=>v+'%' } } } },
    plugins:[monthBandPlugin]
  });
})();
</script>
@endpush
