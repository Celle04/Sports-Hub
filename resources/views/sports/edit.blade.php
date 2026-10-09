@extends('layouts.portal')

@section('content')
    <div class="module-header">
        <div>
            <h1>Edit Sport</h1>
            <p class="page-subtitle">{{ $sport->name }}</p>
        </div>
        <a class="button button-secondary" href="{{ route('sports.show', $sport) }}">Back to Sport</a>
    </div>

    @if ($errors->any())
        <div class="notice notice-error">{{ $errors->first() }}</div>
    @endif

    <form class="card sport-edit-form" method="POST" action="{{ route('sports.update', $sport) }}">
        @csrf
        @method('PUT')
        @include('sports._form', ['sport' => $sport])
    </form>
@endsection