@props([
    'user',
    'size' => 'md',
    'decorative' => false,
])

{{--
    The single source of truth for an athlete's avatar across the portal.

    The uploaded photo always comes from User::profilePhotoUrl(), which reads
    users.profile_photo_path on the public disk, so every avatar in the app
    shows the same stored image. Athletes without an upload get their initials
    instead of a broken image.
--}}
@php
    $photoUrl = $user?->profilePhotoUrl();
@endphp

@if ($photoUrl)
    <img {{ $attributes->class(['avatar', 'avatar--' . $size]) }} src="{{ $photoUrl }}" alt="{{ $decorative ? '' : $user->name . ' profile photo' }}">
@else
    <span {{ $attributes->class(['avatar', 'avatar--' . $size, 'avatar-initials']) }} aria-hidden="true">{{ $user?->initials }}</span>
@endif