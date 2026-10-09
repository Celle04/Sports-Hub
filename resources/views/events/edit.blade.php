@extends('layouts.portal')
@section('content')
<div class="module-header"><div><h1>Edit Event</h1><p class="page-subtitle">Update this scheduled activity</p></div></div>
<form class="card medical-form-body medical-form-card" method="POST" action="{{ route('events.update', $event) }}">@csrf @method('PUT') @include('events._form', ['event' => $event])</form>
@endsection