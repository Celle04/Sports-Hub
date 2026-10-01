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
		<button class="button" type="submit">{{ $announcementItem ? 'Update Announcement' : 'Save Announcement' }}</button>
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
	<div class="table-wrap"><table class="data-table"><thead><tr><th>Title</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead><tbody>
		@forelse ($announcements ?? [] as $row)
			<tr><td><strong>{{ $row->title }}</strong><br><small>{{ Str::limit($row->body, 90) }}</small></td><td><span class="badge">{{ $row->status }}</span></td><td>{{ $row->published_at?->format('M j, Y') ?? 'Unscheduled' }}</td><td><a class="text-link" href="{{ route('admin.announcements', ['announcement_id' => $row->id]) }}">View</a> <a class="text-link" href="{{ route('admin.announcements', ['announcement_id' => $row->id, 'mode' => 'edit']) }}">Edit</a></td></tr>
		@empty
			<tr><td colspan="4" class="empty-cell">No announcements match your search.</td></tr>
		@endforelse
	</tbody></table></div>
</section>
