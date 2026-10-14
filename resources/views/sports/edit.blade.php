@extends('layouts.portal')

@section('content')
    <div class="module-header">
        <div>
            <h1>Edit Sport</h1>
            <p class="page-subtitle">{{ $sport->name }}</p>
        </div>
        <a class="button button-secondary button-icon-only" href="{{ route('sports.show', $sport) }}" aria-label="Back to Sport" title="Back to Sport">
            <svg class="button-icon" aria-hidden="true"><use href="#icon-arrow-left"></use></svg>
        </a>
    </div>

    @if ($errors->any())
        <div class="notice notice-error">{{ $errors->first() }}</div>
    @endif

    <form class="card sport-edit-form" method="POST" action="{{ route('sports.update', $sport) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        @include('sports._form', ['sport' => $sport])
    </form>
@endsection