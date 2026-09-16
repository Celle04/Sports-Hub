@extends('layouts.portal')

@section('content')
<div class="module-header"><div><h1>Edit Sport</h1><p class="page-subtitle">Manage sports categories and classifications</p></div></div>
<form class="card" method="POST" action="{{ route('sports.update', $sport) }}">
    @csrf
    @method('PUT')
    @include('sports._form', ['sport' => $sport])
</form>
@endsection