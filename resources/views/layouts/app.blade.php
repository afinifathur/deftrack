<!doctype html>
<html><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>DEFTRACK</title>
<link rel="stylesheet" href="{{ asset('vendor/deftrack/bootstrap/css/bootstrap.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head><body>
<nav class="navbar navbar-light bg-light border-bottom mb-3">
  <div class="container-fluid">
    <a class="navbar-brand" href="{{ route('dashboard') }}"><strong style="color:#007bff">DEFTRACK</strong> <small class="text-muted">Data-Driven Quality for Manufacturing.</small></a>
    <div class="d-flex gap-2">
      <a class="btn btn-sm btn-outline-primary" href="{{ route('imports.index') }}">Import</a>
      <a class="btn btn-sm btn-outline-primary" href="{{ route('defects.index') }}">Defects</a>
      <a class="btn btn-sm btn-outline-primary" href="{{ route('reports.index') }}">Reports</a>
      <a class="btn btn-sm btn-primary" href="{{ route('settings.index') }}">Settings</a>
    </div>
  </div>
</nav>
<main class="container">@if(session('status'))<div class="alert alert-success">{{ session('status') }}</div>@endif @yield('content')</main>
<script src="{{ asset('vendor/deftrack/jquery/jquery-3.7.1.min.js') }}"></script>
<script src="{{ asset('vendor/deftrack/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/deftrack/chartjs/chart.4.4.0.min.js') }}"></script>
<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body></html>
