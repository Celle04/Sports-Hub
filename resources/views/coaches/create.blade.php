@extends('layouts.portal')

@section('content')
    <div class="module-header">
        <div>
            <h1>Add Coach</h1>
            <p class="page-subtitle">Create a coach record and assign a sport, specialty, and team responsibilities.</p>
        </div>
        <a class="button button-secondary button-icon-only" href="{{ route('coaches.index') }}" aria-label="Back to Coaches" title="Back to Coaches">
            <svg class="button-icon" aria-hidden="true"><use href="#icon-arrow-left"></use></svg>
        </a>
    </div>

    @if ($errors->any())
        <div class="notice notice-error">{{ $errors->first() }}</div>
    @endif

    <form class="card coach-edit-form" method="POST" action="{{ route('coaches.store') }}" enctype="multipart/form-data">
        @csrf
        @include('coaches._form')
    </form>
@endsection
