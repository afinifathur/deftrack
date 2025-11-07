@extends('layouts.app')
@section('content')
<div class="row justify-content-center">
  <div class="col-md-5">
    <div class="card">
      <div class="card-body">
        <h5 class="fw-bold mb-3">Masuk DefTrack</h5>
        @if ($errors->any())
          <div class="alert alert-danger small">{{ $errors->first() }}</div>
        @endif
        <form method="POST" action="{{ route('login.post') }}">
          @csrf
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="remember" id="remember">
            <label class="form-check-label" for="remember">Ingat saya</label>
          </div>
          <div class="d-flex justify-content-between align-items-center">
            <button class="btn btn-primary px-4">Login</button>
            <span class="small text-muted">User awal: <code>password123</code></span>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
