@extends('layouts.portal')

@section('content')
<div class="module-header"><div><h1>Add Coach</h1><p class="page-subtitle">Create a coach record and assign a sport</p></div></div>
@if ($errors->any())<div class="notice notice-error">{{ $errors->first() }}</div>@endif
<form class="card coach-edit-form" method="POST" action="{{ route('coaches.store') }}">@csrf @include('coaches._form')</form>
@endsection
