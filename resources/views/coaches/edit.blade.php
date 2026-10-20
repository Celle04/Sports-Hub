@extends('layouts.portal')

@section('content')
    <div class="module-header">
        <div>
            <h1>Edit Coach</h1>
            <p class="page-subtitle">Update coach information</p>
        </div>
        <a class="button button-secondary button-icon-only" href="{{ route('coaches.show', $coach) }}" aria-label="Back to Coach" title="Back to Coach">
            <svg class="button-icon" aria-hidden="true"><use href="#icon-arrow-left"></use></svg>
        </a>
    </div>

    @if ($errors->any())
        <div class="notice notice-error">{{ $errors->first() }}</div>
    @endif

    <form class="card coach-edit-form" method="POST" action="{{ route('coaches.update', $coach) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('coaches._form', ['coach' => $coach])
    </form>
@endsection