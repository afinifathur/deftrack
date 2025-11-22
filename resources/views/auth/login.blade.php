<!-- resources/views/auth/login.blade.php -->
@extends('layouts.app')

@section('content')
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-7 col-lg-5">

      <div class="card shadow-sm border-0 p-4" style="border-radius: 18px;">

        {{-- LOGO DI ATAS --}}
        <div class="text-center mb-3">
          <img src="{{ asset('images/deftrack-logo.png') }}"
               alt="Logo"
               class="img-fluid mb-2"
               style="max-height: 80px;">
        </div>

        {{-- TITLE --}}
        <div class="text-center mb-4">
          <h5 class="fw-bold mb-1">PT. Peroni Karya Sentra</h5>
          <small class="text-muted">Defect Tracker System</small>
        </div>

        {{-- ERROR --}}
        @if ($errors->any())
          <div class="alert alert-danger small">{{ $errors->first() }}</div>
        @endif

        {{-- FORM --}}
        <form method="POST" action="{{ route('login.post') }}">
          @csrf

          {{-- EMAIL --}}
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input 
              type="email" 
              name="email" 
              value="{{ old('email') }}"
              placeholder="Masukkan email Anda"
              class="form-control @error('email') is-invalid @enderror"
              required autofocus>
            @error('email')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          {{-- PASSWORD --}}
          <div class="mb-3">
            <label class="form-label">Password</label>
            <div class="input-group">
              <input 
                type="password" 
                name="password" 
                id="password"
                placeholder="Masukkan password"
                class="form-control @error('password') is-invalid @enderror"
                required>
              <span class="input-group-text" onclick="togglePassword()" style="cursor:pointer;">
                <i class="bi bi-eye"></i>
              </span>
            </div>
            @error('password')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          {{-- REMEMBER + FORGOT --}}
          <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="remember" id="remember">
              <label class="form-check-label" for="remember">Remember me</label>
            </div>

            @if (Route::has('password.request'))
              <a href="{{ route('password.request') }}" class="small">Lupa password?</a>
            @endif
          </div>

          {{-- SUBMIT --}}
          <div class="d-grid mb-2">
            <button type="submit" class="btn btn-primary py-2 fw-bold">Login</button>
          </div>

          <div class="text-center small text-muted">
            User awal: <code>password123</code>
          </div>
        </form>

      </div>

    </div>
  </div>
</div>

{{-- Script show/hide password --}}
<script>
function togglePassword() {
  const field = document.getElementById('password');
  field.type = field.type === 'password' ? 'text' : 'password';
}
</script>
@endsection
