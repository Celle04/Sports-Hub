<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>Attendance Sheet</title>
	<style>
		* { box-sizing: border-box; }
		body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #1a1a1a; margin: 0; padding: 0; line-height: 1.4; }

		@page {
			size: A4;
			margin: 16mm 14mm 18mm 14mm;
			@bottom-center {
				content: "SNNHS SportsHub &middot; Page " counter(page) " of " counter(pages);
				font-size: 7.5pt;
				color: #777;
				border-top: 0.5px solid #d9d9d9;
				padding-top: 4px;
				width: 100%;
			}
		}

		.header {
			border-bottom: 3px solid #10069F;
			padding-bottom: 10px;
			margin-bottom: 12px;
		}
		.header .school {
			margin: 0 0 2px 0;
			font-size: 12.5pt;
			font-weight: 800;
			color: #10069F;
			letter-spacing: 1px;
			text-transform: uppercase;
		}
		.header .hub {
			margin: 0;
			font-size: 8pt;
			color: #555;
			text-transform: uppercase;
			letter-spacing: 1.5px;
		}
		.header .title {
			margin: 6px 0 2px 0;
			font-size: 16pt;
			color: #1a1a1a;
			font-weight: 800;
			letter-spacing: 1px;
		}
		.header .subtitle {
			margin: 0;
			font-size: 8.5pt;
			color: #555;
		}

		.meta {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 12px;
		}
		.meta td {
			border: 1px solid #d9d9d9;
			padding: 5px 8px;
			font-size: 8.5pt;
			vertical-align: top;
		}
		.meta .label {
			background: #f7f7f8;
			font-weight: 800;
			font-size: 8pt;
			color: #555;
			text-transform: uppercase;
			letter-spacing: .5px;
			width: 24%;
		}
		.meta .value { font-size: 8.5pt; }

		.section {
			font-size: 9pt;
			font-weight: 800;
			color: #10069F;
			text-transform: uppercase;
			letter-spacing: .5px;
			margin: 16px 0 7px;
			border-bottom: 1px solid #10069F;
			padding-bottom: 3px;
		}

		table.data {
			width: 100%;
			border-collapse: collapse;
			margin-bottom: 10px;
		}
		table.data thead { display: table-header-group; }
		table.data tr { page-break-inside: avoid; }
		table.data th {
			background: #10069F;
			color: #fff;
			font-size: 8pt;
			text-align: left;
			padding: 6px;
			border: 1px solid #0C0480;
			font-weight: 800;
			text-transform: uppercase;
			letter-spacing: .3px;
		}
		table.data td {
			border: 1px solid #d9d9d9;
			padding: 5px 6px;
			font-size: 8.5pt;
			vertical-align: top;
		}
		table.data tbody tr:nth-child(even) td { background: #fafafa; }
		table.data td.no { text-align: center; width: 6%; }
		table.data td.student-id { width: 14%; }
		table.data td.name { width: 26%; }
		table.data td.sport { width: 16%; }
		table.data td.status { width: 14%; }
		table.data td.time { width: 14%; }
		table.data td.remarks { width: 30%; }

		.status-badge {
			display: inline-block;
			padding: 2px 7px;
			border-radius: 9px;
			font-size: 7.5pt;
			font-weight: 800;
			letter-spacing: .3px;
		}
		.status-present { color: #177a42; background: #def8e9; }
		.status-absent { color: #d83859; background: #ffe6ed; }
		.status-pending { color: #9b5b00; background: #fff4d6; }
		.status-late { color: #8a5b00; background: #fbe6b0; }
		.status-excused { color: #6a4cca; background: #efe8ff; }

		.signatures {
			margin-top: 20px;
			width: 100%;
		}
		.signatures .sig {
			display: inline-block;
			width: 31%;
			vertical-align: top;
		}
		.signatures .sig-label {
			font-size: 8pt;
			color: #555;
			text-transform: uppercase;
			letter-spacing: .5px;
			margin-bottom: 2px;
		}
		.signatures .sig-line {
			border-top: 1px solid #1a1a1a;
			padding-top: 4px;
			margin-top: 40px;
			font-size: 8pt;
			color: #555;
			text-align: center;
		}

		.empty {
			padding: 26px;
			text-align: center;
			color: #777;
			border: 1px dashed #d9d9d9;
			border-radius: 6px;
		}

		.footer {
			margin-top: 14px;
			font-size: 7.5pt;
			color: #777;
			border-top: 1px solid #d9d9d9;
			padding-top: 7px;
			text-align: center;
		}
	</style>
</head>
<body>
	<div class="header">
		@php
			$logo = public_path('images/snnhs logo.png');
			$hasLogo = file_exists($logo);
		@endphp
		@if ($hasLogo)
			<img src="{{ $logo }}" alt="SNNHS logo" style="width:42px;height:42px;float:left;margin-right:10px;">
		@endif
		<div style="overflow:hidden;">
			<p class="school">Surigao del Norte National High School</p>
			<p class="hub">SNNHS SportsHub</p>
			<p class="title">Attendance Sheet</p>
			<p class="subtitle">Official record of athlete attendance for the session below</p>
		</div>
	</div>

	<table class="meta">
		<tr>
			<td class="label">Session</td>
			<td class="value">{{ $session->displayName() }}</td>
			<td class="label">Sport / Team</td>
			<td class="value">{{ $session->effectiveSportName() }}</td>
		</tr>
		<tr>
			<td class="label">Date</td>
			<td class="value">{{ $session->session_date?->format('F j, Y') ?? 'Not set' }}</td>
			<td class="label">Time</td>
			<td class="value">{{ $session->start_time ? \Illuminate\Support\Carbon::parse($session->start_time)->format('g:i A') : 'Time not set' }}{{ $session->end_time ? ' - '.\Illuminate\Support\Carbon::parse($session->end_time)->format('g:i A') : '' }}</td>
		</tr>
		<tr>
			<td class="label">Venue</td>
			<td class="value">{{ $session->venue ?: 'Not set' }}</td>
			<td class="label">Status</td>
			<td class="value">{{ $session->status }}</td>
		</tr>
		<tr>
			<td class="label">Coach</td>
			<td class="value">{{ $session->event?->coach?->name ?? 'Not assigned' }}</td>
			<td class="label">Total Athletes</td>
			<td class="value">{{ $totals['total'] }}</td>
		</tr>
		<tr>
			<td class="label">Present</td>
			<td class="value">{{ $totals['present'] }}</td>
			<td class="label">Absent</td>
			<td class="value">{{ $totals['absent'] }}</td>
		</tr>
		<tr>
			<td class="label">Late</td>
			<td class="value">{{ $totals['late'] }}</td>
			<td class="label">Pending / Unrecorded</td>
			<td class="value">{{ $totals['pending_unrecorded'] }}</td>
		</tr>
	</table>

	<div class="section">Attendance Roster</div>

	@if ($records->isEmpty())
		<div class="empty">
			No rostered athletes or attendance records have been recorded for this session.
		</div>
	@else
		<table class="data">
			<thead>
				<tr>
					<th class="no">No.</th>
					<th class="student-id">Student ID</th>
					<th class="name">Athlete Name</th>
					<th class="sport">Sport / Team</th>
					<th class="status">Attendance Status</th>
					<th class="time">Time In</th>
					<th class="remarks">Remarks</th>
				</tr>
			</thead>
			<tbody>
				@php($no = 0)
				@foreach ($records as $record)
					@php($no++)
					<tr>
						<td class="no">{{ $no }}</td>
						<td class="student-id">{{ $record->athlete?->student_id ?? '&#8212;' }}</td>
						<td class="name">{{ $record->athlete?->name ?? '&#8212;' }}</td>
						<td class="sport">{{ $record->athlete?->sport?->name ?? $session->effectiveSportName() }}</td>
						<td class="status"><span class="status-badge status-{{ strtolower(str_replace(' ', '-', $record->status)) }}">{{ $record->status }}</span></td>
						<td class="time">{{ $record->check_in_time?->format('g:i A') ?? '&#8212;' }}</td>
						<td class="remarks">&#8212;</td>
					</tr>
				@endforeach
			</tbody>
		</table>
	@endif

	<div class="signatures">
		<div class="sig">
			<div class="sig-label">Prepared by</div>
			<div class="sig-line">{{ $generatedBy }}</div>
		</div>
		<div class="sig">
			<div class="sig-label">Coach / Verified by</div>
			<div class="sig-line">&#8212;</div>
		</div>
		<div class="sig">
			<div class="sig-label">Date</div>
			<div class="sig-line">{{ $generatedAt->format('F j, Y') }}</div>
		</div>
	</div>

	<div class="footer">
		Generated {{ $generatedAt->format('F j, Y \a\t g:i A') }} by {{ $generatedBy }} &middot; SNNHS SportsHub attendance report &middot; {{ $records->count() }} record(s)
	</div>
</body>
</html>
