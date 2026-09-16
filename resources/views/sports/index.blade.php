@extends('layouts.portal')

@section('content')
<div class="module-header">
    <div><h1>Sports Management</h1><p class="page-subtitle">Manage sports categories and classifications</p></div>
    <a class="button" href="{{ route('sports.create') }}">+ Add Sport</a>
</div>

@if (session('success'))<div class="notice">{{ session('success') }}</div>@endif

<div class="card table-wrap">
    <h2 class="panel-title">Sports List</h2>
    <table class="data-table">
        <thead><tr><th>Sport Name</th><th>Classification</th><th>Description</th><th>Actions</th></tr></thead>
        <tbody>
            @forelse ($sports as $sport)
                <tr>
                    <td>{{ $sport->name }}</td>
                    <td>{{ $sport->classification }}</td>
                    <td>{{ $sport->description }}</td>
                    <td>
                        <a class="button" href="{{ route('sports.edit', $sport) }}">Edit</a>
                        <form style="display:inline" method="POST" action="{{ route('sports.destroy', $sport) }}">
                            @csrf
                            @method('DELETE')
                            <button class="button" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">No sports have been added yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection