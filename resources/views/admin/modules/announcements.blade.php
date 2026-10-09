@php($announcementItem = $announcement ?? null)

<div class="grid grid-4">
	<div class="card stat"><small>Total Announcements</small><strong>{{ $summary['total'] ?? 0 }}</strong></div>
	<div class="card stat"><small>Published</small><strong>{{ $summary['published'] ?? 0 }}</strong></div>
	<div class="card stat"><small>Scheduled</small><strong>{{ $summary['scheduled'] ?? 0 }}</strong></div>
	<div class="card stat"><small>Drafts</small><strong>{{ $summary['drafts'] ?? 0 }}</strong></div>
</div>

<section class="card" style="margin-top:18px;padding:18px;">
    <div class="module-header"><div><h2>{{ $announcementItem ? 'Edit announcement' : 'Create announcement' }}</h2><p class="page-subtitle">Keep the sports community informed.</p></div></div>
    <form method="POST" action="{{ $announcementItem ? route('admin.announcements.update', $announcementItem) : route('admin.announcements.store') }}">
        @csrf
        @if ($announcementItem) @method('PUT') @endif
        <div class="grid grid-2">
            <div class="form-group"><label>Title</label><input class="form-control" name="title" value="{{ old('title', $announcementItem?->title ?? '') }}" required></div>
            <div class="form-group"><label>Status</label><select class="form-control" name="status">@foreach ($statuses ?? ['Draft','Published','Scheduled','Archived'] as $status)<option value="{{ $status }}" @selected(old('status', $announcementItem?->status ?? 'Draft') === $status)>{{ $status }}</option>@endforeach</select></div>
        </div>
        <div class="form-group"><label>Announcement details</label><textarea class="form-control" name="body" rows="4" required>{{ old('body', $announcementItem?->body ?? '') }}</textarea></div>
        <div class="grid grid-2">
            <div class="form-group">
                <label for="sport_id">Target</label>
                <select class="form-control" id="sport_id" name="sport_id">
                    <option value="">All Athletes</option>
                    @foreach ($sports ?? [] as $sport)
                        <option value="{{ $sport->id }}" @selected((int) old('sport_id', $announcementItem?->sport_id) === $sport->id)>{{ $sport->name }}</option>
                    @endforeach
                </select>
                <small class="meta">Leave on &ldquo;All Athletes&rdquo; to notify every active athlete, or pick a single sport to notify only that sport&rsquo;s athletes.</small>
            </div>
            <div class="form-group">
                <label for="published_at">Publish date</label>
                <input class="form-control" id="published_at" type="date" name="published_at" value="{{ old('published_at', $announcementItem?->published_at?->toDateString() ?? today()->toDateString()) }}">
                <small class="meta">Athletes are notified when the status is &ldquo;Published&rdquo; and the publish date has arrived.</small>
            </div>
        </div>
        <button class="button" type="submit">{{ $announcementItem ? 'Update Announcement' : 'Post Announcement' }}</button>
        @if ($announcementItem)
            <a class="text-link" href="{{ route('admin.announcements') }}">Cancel</a>
        @endif
    </form>
</section>

<section class="card" style="margin-top:18px;padding:18px;">
    <h2>Announcements</h2>
    <form method="GET" action="{{ route('admin.announcements') }}" class="filters">
        <input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search announcements..." aria-label="Search announcements">
        <select class="form-control" name="status"><option value="">All Status</option>@foreach ($statuses ?? [] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ $status }}</option>@endforeach</select>
        <button class="button" type="submit">Apply Filters</button>
        <a class="button button-secondary" href="{{ route('admin.announcements') }}">Reset</a>
    </form>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Title</th><th>Target</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead><tbody>
        @forelse ($announcements ?? [] as $row)
            <tr><td><strong>{{ $row->title }}</strong><br><small>{{ Str::limit($row->body, 90) }}</small></td><td>{{ $row->sport?->name ?? 'All Athletes' }}</td><td><span class="badge">{{ $row->status }}</span></td><td>{{ $row->published_at?->format('M j, Y') ?? 'Unscheduled' }}</td><td>
                <a class="text-link" href="{{ route('admin.announcements', ['announcement_id' => $row->id]) }}">Edit</a>
                @if ($row->status !== 'Published')
                    <form method="POST" action="{{ route('admin.announcements.publish', $row) }}" style="display:inline">@csrf<button class="text-link" type="submit">Publish</button></form>
                @else
                    <form method="POST" action="{{ route('admin.announcements.archive', $row) }}" style="display:inline">@csrf<button class="text-link" type="submit">Unpublish</button></form>
                @endif
                <button class="text-link" type="button" data-confirm-dialog data-confirm-title="Delete Announcement?" data-confirm-message="Delete this announcement? This action cannot be undone." data-confirm-label="Delete" data-confirm-method="DELETE" data-confirm-url="{{ route('admin.announcements.destroy', $row) }}">Delete</button>
            </td></tr>
        @empty
            <tr><td colspan="5" class="empty-cell">No announcements match your search.</td></tr>
        @endforelse
    </tbody></table></div>
</section>
