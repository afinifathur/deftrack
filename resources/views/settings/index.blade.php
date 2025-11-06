@extends('layouts.app')
@section('content')
<h5>Settings</h5>
<div class="row g-3">
  <div class="col-md-6">
    <div class="card"><div class="card-body">
      <h6>Departemen</h6>
      <form method="POST" action="{{ route('settings.departments.store') }}" class="row g-2 mb-2">@csrf
        <div class="col"><input class="form-control" name="name" placeholder="Nama" required></div>
        <div class="col"><input class="form-control" name="code" placeholder="Kode"></div>
        <div class="col-auto"><button class="btn btn-primary">Tambah</button></div>
      </form>
      <table class="table table-sm">
        @foreach($departments as $d)
        <tr><td>{{ $d->name }}</td><td>{{ $d->code }}</td>
          <td><form method="POST" action="{{ route('settings.departments.toggle',$d) }}">@csrf @method('PATCH')
            <button class="btn btn-sm {{ $d->is_active ? 'btn-success' : 'btn-secondary' }}">{{ $d->is_active ? 'Aktif' : 'Nonaktif' }}</button>
          </form></td></tr>
        @endforeach
      </table>
    </div></div>
  </div>
  <div class="col-md-6">
    <div class="card"><div class="card-body">
      <h6>Kategori & Subkategori Kerusakan</h6>
      <form method="POST" action="{{ route('settings.types.store') }}" class="row g-2 mb-2">@csrf
        <div class="col"><input class="form-control" name="name" placeholder="Nama" required></div>
        <div class="col">
          <select class="form-select" name="parent_id">
            <option value="">(Kategori Utama)</option>
            @foreach($types->where('parent_id', null) as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
          </select>
        </div>
        <div class="col-auto"><button class="btn btn-primary">Tambah</button></div>
      </form>
      <ul>
        @foreach($types->where('parent_id', null) as $t)
          <li>{{ $t->name }}<ul>
            @foreach($types->where('parent_id', $t->id) as $c)<li>{{ $c->name }}</li>@endforeach
          </ul></li>
        @endforeach
      </ul>
    </div></div>
  </div>
</div>
@endsection
