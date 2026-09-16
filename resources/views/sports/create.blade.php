@extends('layouts.portal')

@section('content')
<div class="module-header"><div><h1>Add Sport</h1><p class="page-subtitle">Manage sports categories and classifications</p></div></div>
<form class="card" method="POST" action="{{ route('sports.store') }}">
    @csrf
    @include('sports._form')
</form>
@endsection