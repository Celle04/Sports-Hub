@extends('layouts.portal')

@section('content')
    <div class="module-header">
        <div>
            <h1>Create Athlete Account</h1>
            <p class="page-subtitle">Issue the official athlete login for an approved application</p>
        </div>
        <div class="event-header-actions">
            <a class="button button-secondary" href="{{ route('applications.show', $application) }}">Back to Application</a>
            <a class="button button-secondary" href="{{ route('athletes.index') }}">Back to Athletes</a>
        </div>
    </div>

    @if (session('success'))
        <div class="notice">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="notice notice-error">{{ $errors->first() }}</div>
    @endif

    <section class="card">
        <div class="medical-card-head">
            <div>
                <span class="admin-dashboard-kicker">APPLICATION</span>
                <h2>Application Accepted</h2>
                <p class="meta">Details carried over from the approved application.</p>
            </div>
        </div>
        <div class="medical-fact-grid">
            <div class="medical-fact"><span>Applicant</span><strong>{{ $application->name }}</strong></div>
            <div class="medical-fact"><span>Application email</span><strong>{{ $application->email ?: 'Not provided' }}</strong></div>
            <div class="medical-fact"><span>Student ID</span><strong>{{ $application->student_id ?: 'Not provided' }}</strong></div>
            <div class="medical-fact"><span>Grade</span><strong>{{ $application->grade ?: 'Not provided' }}</strong></div>
            <div class="medical-fact"><span>Gender</span><strong>{{ $application->gender ?: 'Not provided' }}</strong></div>
            <div class="medical-fact"><span>Sport</span><strong>{{ $application->sportCategory?->name ?? $application->sport ?? 'Unassigned' }}</strong></div>
            <div class="medical-fact"><span>Status</span><strong><span class="badge application-status-{{ strtolower(str_replace(' ', '-', $application->status)) }}">{{ $application->status }}</span></strong></div>
            <div class="medical-fact"><span>Accepted on</span><strong>{{ $application->reviewed_at?->format('F j, Y') ?? 'Not recorded' }}</strong></div>
        </div>
    </section>

    @if ($application->status !== 'Approved')
        <section class="card athlete-edit-form">
            <h2>Account Not Available</h2>
            <p class="empty-state">Approve this application before creating the athlete account.</p>
        </section>
    @elseif ($application->athlete)
        <section class="card athlete-edit-form">
            <h2>Account Created</h2>
            <p class="empty-state">An athlete account has already been created for this application.</p>
            <div class="event-actions">
                <a class="button" href="{{ route('athletes.show', $application->athlete) }}">View Athlete Profile</a>
            </div>
        </section>
    @else
        <form method="POST" action="{{ route('athletes.store') }}">
            @csrf
            <input type="hidden" name="application_id" value="{{ $application->id }}">

            <section class="card athlete-edit-form">
                <div class="medical-card-head">
                    <div>
                        <span class="admin-dashboard-kicker">CREDENTIALS</span>
                        <h2>Account Details</h2>
                        <p class="meta">The athlete signs in with the email below using the username and temporary password you set.</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Full Name</label>
                        <input class="form-control" id="name" type="text" value="{{ $application->name }}" readonly>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input
                            class="form-control @error('email') is-invalid @enderror"
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email', $application->email) }}"
                            placeholder="{{ $application->email ?: 'Enter the athlete email' }}"
                            required
                        >
                        <small class="form-hint">Pre-filled from the athlete's application. Edit it only if the applicant gave a wrong address.</small>
                        @error('email')<small class="form-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group">
                        <label for="student_id">Student ID</label>
                        <input class="form-control" id="student_id" type="text" value="{{ $application->student_id }}" readonly>
                    </div>
                    <div class="form-group">
                        <label for="sport_id">Sport</label>
                        <input class="form-control" id="sport_id" type="text" value="{{ $application->sportCategory?->name ?? $application->sport ?? 'Unassigned' }}" readonly>
                    </div>
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input
                            class="form-control @error('username') is-invalid @enderror"
                            id="username"
                            name="username"
                            type="text"
                            value="{{ old('username') }}"
                            required
                        >
                        @error('username')<small class="form-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group">
                        <label for="role">Role</label>
                        <input class="form-control" id="role" type="text" value="Student" readonly>
                    </div>
                    <div class="form-group">
                        <label for="password">Temporary Password</label>
                        <input
                            class="form-control @error('password') is-invalid @enderror"
                            id="password"
                            name="password"
                            type="password"
                            minlength="8"
                            required
                            autocomplete="new-password"
                        >
                        @error('password')<small class="form-error">{{ $message }}</small>@enderror
                    </div>
                    <div class="form-group">
                        <label for="password_confirmation">Confirm Password</label>
                        <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" minlength="8" required autocomplete="new-password">
                    </div>
                </div>

                <div class="event-actions">
                    <button class="button" type="submit">Create Athlete Account</button>
                    <a class="button button-muted" href="{{ route('applications.show', $application) }}">Cancel</a>
                </div>
            </section>
        </form>
    @endif
@endsection