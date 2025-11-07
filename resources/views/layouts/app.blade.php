{{-- resources/views/layouts/app.blade.php --}}
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>DEFTRACK</title>
  <link rel="stylesheet" href="{{ asset('vendor/deftrack/bootstrap/css/bootstrap.min.css') }}">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="bg-body">
<nav class="navbar navbar-expand-lg navbar-dark shadow-sm sticky-top" style="background: var(--primary);">
  <div class="container-fluid">
    <a class="navbar-brand fw-bold tracking-1" href="{{ route('dashboard') }}">
      <span class="logo-text">DEFTRACK</span>
      <small class="ms-2 opacity-75 d-none d-md-inline">Data-Driven Quality for Manufacturing.</small>
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNav" aria-controls="topNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    {{-- RIGHT AREA: menu + auth --}}
    <div class="collapse navbar-collapse" id="topNav">
      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item"><a class="nav-link" href="{{ route('imports.index') }}">Import</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('defects.index') }}">Defects</a></li>
        <li class="nav-item"><a class="nav-link" href="{{ route('reports.index') }}">Reports</a></li>
        <li class="nav-item">
          <a class="nav-link btn btn-sm btn-light text-primary ms-2 px-3" href="{{ route('settings.index') }}">
            Settings
          </a>
        </li>

        @auth
          <li class="nav-item ms-3">
            <span class="badge bg-light text-dark">{{ auth()->user()->role }}</span>
          </li>
          <li class="nav-item ms-2">
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button type="submit" class="btn btn-outline-light btn-sm">Logout</button>
            </form>
          </li>
        @else
          <li class="nav-item ms-3">
            <a class="btn btn-outline-light btn-sm" href="{{ route('login') }}">Login</a>
          </li>
        @endauth
      </ul>
    </div>
  </div>
</nav>

<main class="container py-4">
  @if(session('status'))
    <div class="alert alert-success shadow-sm rounded-3">{{ session('status') }}</div>
  @endif
  @yield('content')
</main>

<script src="{{ asset('vendor/deftrack/jquery/jquery-3.7.1.min.js') }}"></script>
<script src="{{ asset('vendor/deftrack/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('vendor/deftrack/chartjs/chart.4.4.0.min.js') }}"></script>
<script src="{{ asset('js/app.js') }}"></script>
@stack('scripts')
</body>
</html>
