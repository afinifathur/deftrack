@extends('layouts.app')

@section('content')
<h4 class="mb-3">Recycle Bin — Defects</h4>

<div class="card">
    <div class="card-body">
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Tanggal</th>
                    <th>Departemen</th>
                    <th>Dihapus Pada</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            @foreach($deleted as $d)
                <tr>
                    <td>{{ $d->id }}</td>
                    <td>{{ $d->date }}</td>
                    <td>{{ optional($d->department)->name }}</td>
                    <td>{{ $d->deleted_at }}</td>
                    <td>
                        <form action="{{ route('defects.restore',$d->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-success"
                               onclick="return confirm('Restore defect ini?')">
                                Restore
                            </button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>

        {{ $deleted->links() }}
    </div>
</div>
@endsection
