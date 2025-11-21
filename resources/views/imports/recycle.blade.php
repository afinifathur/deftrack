@extends('layouts.app')

@section('content')
<div class="container">
    <h3 class="mb-4">♻️ Recycle Bin — Import Sessions</h3>

    @if ($deleted->isEmpty())
        <div class="alert alert-info">Tidak ada data yang dihapus.</div>
    @else
    <table class="table table-bordered">
        <thead class="table-light">
            <tr>
                <th>Tanggal</th>
                <th>Departemen</th>
                <th>Total Batch</th>
                <th>Dihapus Pada</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($deleted as $item)
            <tr>
                <td>{{ $item->date }}</td>
                <td>{{ $item->department->name ?? '-' }}</td>
                <td>{{ $item->batches_count }}</td>
                <td>{{ $item->deleted_at }}</td>
                <td>
                    <form action="{{ route('imports.restore', $item->id) }}" method="POST" class="d-inline">
                        @csrf
                        <button class="btn btn-success btn-sm">Restore</button>
                    </form>

                    <form action="{{ route('imports.forceDelete', $item->id) }}" method="POST" class="d-inline"
                        onsubmit="return confirm('Hapus permanen? Semua batch akan hilang!')">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger btn-sm">Hapus Permanen</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>
@endsection
