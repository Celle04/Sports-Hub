@extends('layouts.portal')

@section('content')
<div class="module-header">
    <div>
        <h1>{{ $heading }}</h1>
        <p class="page-subtitle">{{ $subtitle }}</p>
    </div>
    @if ($page === 'events')
        <a class="button" href="{{ route('events.create') }}">Create Event</a>
    @elseif ($page === 'sports')
        <a class="button" href="{{ route('sports.create') }}">Add New Sport</a>
    @elseif ($page === 'medical')
        <a class="button" href="{{ route('admin.medical') }}#medical-record-form">Add Medical Record</a>
    @endif
</div>

@if (session('success'))
    <div class="notice">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="notice">Please correct the highlighted form fields.</div>
@endif

@if (in_array($page, ['dashboard', 'calendar', 'announcements', 'attendance', 'medical', 'achievements', 'achievement-certificate', 'certificate-requests', 'reports'], true))
    @include('admin.modules.'.$page)
@else
    <div class="card"><p>Use the navigation to manage this module.</p></div>
@endif
@endsection
