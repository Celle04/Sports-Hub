@extends('layouts.portal')

@section('content')
<div class="module-header">
    <div>
        <h1>{{ $heading }}</h1>
        <p class="page-subtitle">{{ $subtitle }}</p>
    </div>
</div>

@if (session('success'))
    <div class="notice">{{ session('success') }}</div>
@endif

@if ($errors->any())
    <div class="notice">Please correct the highlighted form fields.</div>
@endif

@if (in_array($page, ['dashboard', 'sports', 'calendar', 'schedule', 'announcements', 'attendance', 'application', 'coach', 'profile'], true))
    @include('student.modules.'.$page)
@else
    <div class="card"><p>Use the navigation to access this section.</p></div>
@endif
@endsection
