@extends('layouts.portal')

@section('content')
    <div class="module-header">
        <div>
            <h1>Add Sport</h1>
            <p class="page-subtitle">Create a new sport category</p>
        </div>
        <a class="button button-secondary" href="{{ route('sports.index') }}">Back to Sports</a>
    </div>

    @if ($errors->any())
        <div class="notice notice-error">{{ $errors->first() }}</div>
    @endif

    <form class="card sport-edit-form" method="POST" action="{{ route('sports.store') }}">
        @csrf
        @include('sports._form')
    </form>
@endsection