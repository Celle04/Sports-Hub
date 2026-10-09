@extends('layouts.portal')
@section('content')
<div class="module-header"><div><h1>Create Event</h1><p class="page-subtitle">Schedule a sports activity</p></div></div>
<form class="card medical-form-body medical-form-card" method="POST" action="{{ route('events.store') }}">@csrf @include('events._form')</form>
@endsection