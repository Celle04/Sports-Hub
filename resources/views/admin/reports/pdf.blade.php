<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<title>{{ $payload['title'] }}</title>
	<style>
		* { box-sizing: border-box; }
		body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; margin: 0; padding: 24px; }
		.header { text-align: center; border-bottom: 2px solid #8f1238; padding-bottom: 12px; margin-bottom: 14px; }
		.header .school { font-size: 12px; font-weight: bold; letter-spacing: 1px; margin: 0; }
		.header h1 { font-size: 20px; color: #8f1238; margin: 4px 0; letter-spacing: 2px; }
		.header h2 { font-size: 14px; margin: 6px 0 2px; text-transform: uppercase; }
		.header .meta { font-size: 10px; color: #555; margin: 4px 0 0; }
		.summary { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
		.summary td { border: 1px solid #d9d9d9; padding: 6px 8px; }
		.summary .label { background: #f7f7f8; color: #555; font-size: 10px; text-transform: uppercase; letter-spacing: .5px; width: 20%; }
		.summary .value { font-weight: bold; font-size: 12px; }
		table.data { width: 100%; border-collapse: collapse; }
		table.data thead { display: table-header-group; }
		table.data tr { page-break-inside: avoid; }
		table.data th { background: #8f1238; color: #fff; font-size: 9.5px; text-align: left; padding: 6px 6px; border: 1px solid #7a0f30; }
		table.data td { border: 1px solid #d9d9d9; padding: 5px 6px; font-size: 10px; vertical-align: top; }
		table.data tbody tr:nth-child(even) td { background: #fafafa; }
		.empty { padding: 18px; text-align: center; color: #777; border: 1px dashed #d9d9d9; }
		.footer { margin-top: 16px; font-size: 9px; color: #777; border-top: 1px solid #d9d9d9; padding-top: 8px; }
	</style>
</head>
<body>
	<div class="header">
		<p class="school">SURIGAO DEL NORTE NATIONAL HIGH SCHOOL</p>
		<h1>SPORTSHUB</h1>
		<h2>{{ $payload['title'] }}</h2>
		<p class="meta">Generated {{ $generatedAt->format('F j, Y \a\t g:i A') }} by {{ $generatedBy }}</p>
		<p class="meta">Filters: {{ $payload['filtersLabel'] }}</p>
	</div>

	<table class="summary">
		<tr>
			@foreach ($payload['summary'] as $item)
				<td style="text-align:center; padding:8px 6px;">
					<div style="font-size:16px; font-weight:bold;">{{ $item['value'] }}</div>
					<div style="font-size:9px; color:#555; text-transform:uppercase; letter-spacing:.5px;">{{ $item['label'] }}</div>
				</td>
			@endforeach
		</tr>
	</table>

	@if ($payload['rows']->isEmpty())
		<div class="empty">{{ $payload['emptyMessage'] }}</div>
	@else
		<table class="data">
			<thead>
				<tr>
					@foreach ($payload['columns'] as $column)
						<th>{{ $column['label'] }}</th>
					@endforeach
				</tr>
			</thead>
			<tbody>
				@foreach ($payload['rows'] as $row)
					<tr>
						@foreach ($payload['columns'] as $column)
							<td>{{ $column['key'] === 'rate' ? ($row[$column['key']] ?? 0).'%' : ($row[$column['key']] ?? '—') }}</td>
						@endforeach
					</tr>
				@endforeach
			</tbody>
		</table>
	@endif

	<div class="footer">
		{{ $payload['rows']->count() }} record(s) &middot; Attendance rate counts Present and Late over counted records only; Pending rows and cancelled sessions are excluded.
		&middot; Printed by {{ $generatedBy }}.
	</div>
</body>
</html>
