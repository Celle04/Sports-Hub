@extends('layouts.portal')
@section('content')
<div class="module-header"><div><h1>Edit Event</h1><p class="page-subtitle">Update this scheduled activity</p></div></div>
<form class="card medical-form-body medical-form-card" method="POST" action="{{ route('events.update', $event) }}">@csrf @method('PUT') @include('events._form', ['attendanceMode' => 'link', 'attendancePrefix' => null, 'attendanceFormId' => 'event-attendance-form'])</form>

{{--
    The attendance pop-out card on the edit screen posts to its own form, so a
    new session can be added to an event that already exists without touching
    the event itself.
--}}
<form id="event-attendance-form" method="POST" action="{{ route('admin.attendance.sessions.store') }}" hidden>
    @csrf
    <input type="hidden" name="event_id" value="{{ $event->id }}">
</form>
@endsection
