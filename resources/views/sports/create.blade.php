@extends('layouts.portal')

@section('content')
    <div class="module-header">
        <div>
            <h1>Add Sport</h1>
            <p class="page-subtitle">Create a new sport category</p>
        </div>
        <a class="button button-secondary button-icon-only" href="{{ route('sports.index') }}" aria-label="Back to Sports" title="Back to Sports">
            <svg class="button-icon" aria-hidden="true"><use href="#icon-arrow-left"></use></svg>
        </a>
    </div>

    @if ($errors->any())
        <div class="notice notice-error">{{ $errors->first() }}</div>
    @endif

    <form class="card sport-edit-form" method="POST" action="{{ route('sports.store') }}" enctype="multipart/form-data">
        @csrf
        @include('sports._form')
    </form>
@endsection