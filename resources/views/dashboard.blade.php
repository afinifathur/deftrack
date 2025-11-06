@extends('layouts.app')
@section('content')
<h5>Dashboard (6 bulan)</h5>
<div class="alert alert-info">KPI % Defect: <strong>{{ $kpi }}%</strong></div>
<div class="row g-3">
  <div class="col-md-6"><div class="card"><div class="card-body"><h6>PCS per Minggu</h6><canvas id="chartPcs"></canvas></div></div></div>
  <div class="col-md-6"><div class="card"><div class="card-body"><h6>KG per Minggu</h6><canvas id="chartKg"></canvas></div></div></div>
  <div class="col-md-6"><div class="card"><div class="card-body"><h6>Top Defect</h6><canvas id="chartDonut"></canvas></div></div></div>
  <div class="col-md-6"><div class="card"><div class="card-body"><h6>Trend per Departemen</h6><canvas id="chartDept"></canvas></div></div></div>
</div>
@push('scripts')
<script>
new Chart(document.getElementById('chartPcs'),{type:'line',data:{labels:[],datasets:[{label:'PCS',data:[]}]}});
new Chart(document.getElementById('chartKg'),{type:'line',data:{labels:[],datasets:[{label:'KG',data:[]}]}});
new Chart(document.getElementById('chartDonut'),{type:'doughnut',data:{labels:[],datasets:[{data:[]}]}});
new Chart(document.getElementById('chartDept'),{type:'line',data:{labels:[],datasets:[]}});
</script>
@endpush
@endsection
