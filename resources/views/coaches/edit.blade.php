@extends('layouts.portal')

@section('content')
<div class="module-header"><div><h1>Edit Coach</h1><p class="page-subtitle">Update coach information and assignment</p></div></div>
@if ($errors->any())<div class="notice notice-error">{{ $errors->first() }}</div>@endif
<form class="card coach-edit-form" method="POST" action="{{ route('coaches.update', $coach) }}">@csrf @method('PUT') @include('coaches._form', ['coach' => $coach])</form>
@endsection
