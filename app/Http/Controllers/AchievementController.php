<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\Event;
use App\Models\Sport;
use App\Models\User;
use App\Services\StudentUpdateNotifier;
use App\Services\AchievementCertificateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AchievementController extends Controller
{
    /**
     * The folder certificates are written to on the private disk.
     */
    private const CERTIFICATE_FOLDER = 'achievement-certificates';

    public function index(Request $request): View
    {
        $this->ensureAdministrator();

        return view('admin.page', array_merge($this->portalData(), [
            'page' => 'achievements',
            'heading' => 'Achievements',
            'subtitle' => 'Record and manage athlete accomplishments and awards',
            'achievementFormMode' => 'create',
            'achievementFormRecord' => null,
            'selectedAchievement' => null,
            'achievementViewMode' => 'index',
            'achievementSummary' => $this->summary(),
            ...$this->listData($request),
        ]));
    }

    public function create(Request $request): View
    {
        $this->ensureAdministrator();

        return $this->index($request);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdministrator();

        $validated = $this->validateAchievement($request);
        $athlete = User::where('role', 'Student')->findOrFail($validated['athlete_id']);

        $achievement = Achievement::create($this->achievementAttributes(
            $validated,
            $this->storeCertificate($request),
        ));

        $this->notifyAthlete($achievement);

        return redirect()->route('admin.achievements')->with('success', 'Achievement added successfully.');
    }

    public function show(Achievement $achievement): View
    {
        $this->ensureAdministrator();

        $achievement->load(['athlete.sport', 'sport', 'event']);

        return view('admin.page', array_merge($this->portalData(), [
            'page' => 'achievements',
            'heading' => 'Achievement Details',
            'subtitle' => 'Review a recorded accomplishment',
            'achievementFormMode' => 'create',
            'achievementFormRecord' => null,
            'selectedAchievement' => $achievement,
            'achievementViewMode' => 'show',
            'achievementSummary' => $this->summary(),
            ...$this->listData(request()),
        ]));
    }

    public function edit(Achievement $achievement, Request $request): View
    {
        $this->ensureAdministrator();

        $achievement->load(['athlete.sport', 'sport', 'event']);

        return view('admin.page', array_merge($this->portalData(), [
            'page' => 'achievements',
            'heading' => 'Achievements',
            'subtitle' => 'Record and manage athlete accomplishments and awards',
            'achievementFormMode' => 'edit',
            'achievementFormRecord' => $achievement,
            'selectedAchievement' => null,
            'achievementViewMode' => 'index',
            'achievementSummary' => $this->summary(),
            ...$this->listData($request, $achievement),
        ]));
    }

    public function update(Request $request, Achievement $achievement): RedirectResponse
    {
        $this->ensureAdministrator();

        $validated = $this->validateAchievement($request, $achievement);

        if ($request->hasFile('certificate')) {
            $this->deleteCertificate($achievement);
        }

        $achievement->update($this->achievementAttributes($validated, $this->storeCertificate($request), $achievement));

        return redirect()->route('admin.achievements')->with('success', 'Achievement updated successfully.');
    }

    public function destroy(Achievement $achievement): RedirectResponse
    {
        $this->ensureAdministrator();

        $this->deleteCertificate($achievement);
        $achievement->delete();

        return redirect()->route('admin.achievements')->with('success', 'Achievement deleted successfully.');
    }

    /**
     * Serve a certificate from the private disk. The file name is never taken
     * from the request, so a caller cannot reach an arbitrary path.
     */
    public function certificate(Achievement $achievement)
    {
        $this->ensureAdministrator();

        abort_unless($achievement->hasCertificate(), 404, 'No certificate is attached to this achievement.');

        return Storage::disk('private')->download($achievement->certificate_path, $achievement->certificateDiskName());
    }

    /**
     * Certificate generator: pick an existing achievement and produce its
     * official printable certificate.
     */
    public function generate(Request $request): View
    {
        $this->ensureAdministrator();

        $selectedId = $request->integer('achievement');

        $achievements = Achievement::query()
            ->with(['athlete:id,name,student_id', 'sport:id,name', 'event:id,title'])
            ->orderByDesc('date_achieved')
            ->orderByDesc('id')
            ->get();

        return view('admin.page', array_merge($this->portalData(['title' => 'Achievement Certificate']), [
            'page' => 'achievement-certificate',
            'heading' => 'Achievement Certificate',
            'subtitle' => 'Pick an achievement to generate its official certificate',
            'certificateAchievement' => $selectedId ? $achievements->firstWhere('id', $selectedId) : null,
            'certificateAchievements' => $achievements,
        ]));
    }

    /**
     * Browser version of the certificate: a printable A4 page plus a small
     * toolbar for printing or downloading the PDF.
     */
    public function renderCertificate(Achievement $achievement): View
    {
        $this->ensureAdministrator();

        $achievement->load(['athlete:id,name,student_id', 'sport:id,name', 'event:id,title']);

        return view('admin.achievements.certificate', app(AchievementCertificateService::class)->data($achievement, false));
    }

    /**
     * Download the achievement certificate as a PDF.
     */
    public function downloadCertificate(Achievement $achievement)
    {
        $this->ensureAdministrator();

        $achievement->load(['athlete:id,name,student_id', 'sport:id,name', 'event:id,title']);

        $data = app(AchievementCertificateService::class)->data($achievement, true);

        return Pdf::loadView('admin.achievements.certificate', $data)
            ->setPaper('a4', 'landscape')
            ->download($data['filename']);
    }

/**
     * Everything the module list needs, filtered through the query builder.
     *
     * @return array<string, mixed>
     */
    private function listData(Request $request, ?Achievement $editing = null): array
    {
        $search = trim((string) $request->input('search', ''));
        $sportId = $request->input('sport_id');
        $type = $request->input('achievement_type');
        $athleteId = $request->input('athlete_id');
        $from = $request->input('date_from');
        $to = $request->input('date_to');
        $sort = in_array($request->input('sort'), ['oldest', 'title'], true) ? $request->input('sort') : 'newest';

        $achievements = Achievement::query()
            ->with(['athlete.sport', 'sport', 'event'])
            ->when($search, fn ($query, $value) => $query->where(function ($searchQuery) use ($value) {
                $searchQuery->where('title', 'like', "%{$value}%")
                    ->orWhere('competition', 'like', "%{$value}%")
                    ->orWhere('place', 'like', "%{$value}%")
                    ->orWhereHas('athlete', fn ($athleteQuery) => $athleteQuery
                        ->where('name', 'like', "%{$value}%")
                        ->orWhere('student_id', 'like', "%{$value}%"));
            }))
            ->when($sportId, fn ($query, $value) => $query->where('sport_id', $value))
            ->when($type, fn ($query, $value) => $query->where('achievement_type', $value))
            ->when($athleteId, fn ($query, $value) => $query->where('athlete_id', $value))
            ->when($from, fn ($query, $value) => $query->whereDate('date_achieved', '>=', $value))
            ->when($to, fn ($query, $value) => $query->whereDate('date_achieved', '<=', $value))
            ->when($sort === 'oldest', fn ($query) => $query->orderBy('date_achieved'))
            ->when($sort === 'title', fn ($query) => $query->orderBy('title'))
            ->orderByDesc('date_achieved')
            ->orderByDesc('id')
            ->get();

        return [
            'achievements' => $achievements,
            'achievementAthletes' => User::where('role', 'Student')->orderBy('name')->get(),
            'achievementSports' => Sport::orderBy('name')->get(),
            'achievementEvents' => Event::with('sport')->orderByDesc('starts_at')->get(),
            'achievementTypes' => Achievement::TYPES,
            'achievementSearch' => $search,
            'achievementSportFilter' => $sportId,
            'achievementTypeFilter' => $type,
            'achievementAthleteFilter' => $athleteId,
            'achievementDateFrom' => $from,
            'achievementDateTo' => $to,
            'achievementSort' => $sort,
            'achievementEditing' => $editing,
        ];
    }

    /**
     * Counts used by the summary strip and the dashboard card.
     *
     * @return array<string, int>
     */
    private function summary(): array
    {
        $counts = Achievement::query()
            ->selectRaw('achievement_type, COUNT(*) as total')
            ->groupBy('achievement_type')
            ->pluck('total', 'achievement_type');

        return [
            'total' => (int) $counts->sum(),
            'gold' => (int) $counts->get('Gold Medal', 0),
            'silver' => (int) $counts->get('Silver Medal', 0),
            'bronze' => (int) $counts->get('Bronze Medal', 0),
            'titles' => (int) $counts->get('Champion', 0) + (int) $counts->get('Runner-up', 0),
            'athletes' => Achievement::query()->distinct()->count('athlete_id'),
            'this_year' => Achievement::query()->whereYear('date_achieved', now()->year)->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateAchievement(Request $request, ?Achievement $achievement = null): array
    {
        $validator = Validator::make($request->all(), [
            'athlete_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'Student')),
            ],
            'sport_id' => ['nullable', 'exists:sports,id'],
            'event_id' => ['nullable', 'exists:events,id'],
            'title' => ['required', 'string', 'max:150'],
            'achievement_type' => ['required', 'string', 'max:60'],
            'competition' => ['nullable', 'string', 'max:150'],
            'place' => ['nullable', 'string', 'max:60'],
            'date_achieved' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:2000'],
            'certificate' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:2048'],
        ], [
            'athlete_id.required' => 'Select the athlete who earned this achievement.',
            'athlete_id.exists' => 'The selected athlete no longer exists.',
            'title.required' => 'Enter an achievement title.',
            'achievement_type.required' => 'Enter an achievement type.',
            'date_achieved.required' => 'Enter the date the achievement was earned.',
            'certificate.mimes' => 'The certificate must be a JPG, JPEG, PNG, WEBP or PDF file.',
            'certificate.max' => 'The certificate may not be larger than 2 MB.',
        ]);

        $validator->after(function ($validator) use ($request) {
            $this->validateRelationships($validator, $request);
        });

        return $validator->validate();
    }

    /**
     * An achievement may only be filed under a sport the athlete actually plays,
     * and under an event that belongs to that same sport.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    private function validateRelationships($validator, Request $request): void
    {
        $athlete = User::where('role', 'Student')->find($request->input('athlete_id'));

        if (! $athlete) {
            return;
        }

        $sportId = $request->input('sport_id');

        if ($sportId && ! in_array((int) $sportId, $athlete->sportIds(), true)) {
            $validator->errors()->add('sport_id', 'That sport is not assigned to the selected athlete.');
        }

        $eventId = $request->input('event_id');

        if ($eventId) {
            $event = Event::find($eventId);

            if (! $event) {
                $validator->errors()->add('event_id', 'The selected event no longer exists.');
            } elseif ($sportId && (int) $event->sport_id !== (int) $sportId) {
                $validator->errors()->add('event_id', 'The selected event does not belong to the selected sport.');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function achievementAttributes(array $validated, ?string $storedCertificate = null, ?Achievement $achievement = null): array
    {
        $event = filled($validated['event_id'] ?? null) ? Event::find($validated['event_id']) : null;

        return [
            'athlete_id' => $validated['athlete_id'],
            'sport_id' => $validated['sport_id'] ?? ($event?->sport_id ?: null),
            'event_id' => $validated['event_id'] ?? null,
            'title' => $validated['title'],
            'achievement_type' => $validated['achievement_type'],
            'competition' => $validated['competition'] ?? ($event?->title),
            'place' => $validated['place'] ?? null,
            'date_achieved' => $validated['date_achieved'],
            'description' => $validated['description'] ?? null,
            'certificate_path' => $storedCertificate ?? $achievement?->certificate_path,
        ];
    }

    /**
     * Tell the athlete they were awarded something.
     *
     * The dedupe key is keyed on the achievement id, so re-saving an edit or
     * refreshing the page can never deliver the same award twice.
     */
    private function notifyAthlete(Achievement $achievement): void
    {
        $achievement->loadMissing('athlete.sport');

        app(StudentUpdateNotifier::class)->notifyStudent(
            $achievement->athlete,
            'New Achievement',
            'You received a new achievement: '.$achievement->title,
            route('student.achievements.show', $achievement),
            [
                'achievement_id' => $achievement->id,
                'achievement_title' => $achievement->title,
                'dedupe_key' => 'achievement|'.$achievement->id,
            ],
        );
    }

    private function storeCertificate(Request $request): ?string
    {
        if (! $request->hasFile('certificate')) {
            return null;
        }

        $file = $request->file('certificate');

        return $file->storeAs(
            self::CERTIFICATE_FOLDER,
            Str::uuid().'.'.strtolower($file->getClientOriginalExtension() ?: 'pdf'),
            'private',
        );
    }

    private function deleteCertificate(Achievement $achievement): void
    {
        if ($achievement->certificate_path && Storage::disk('private')->exists($achievement->certificate_path)) {
            Storage::disk('private')->delete($achievement->certificate_path);
        }
    }

    private function portalData(array $data = []): array
    {
        return array_merge([
            'title' => 'Achievements',
            'navItems' => [
                ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'route' => 'dashboard'],
                ['key' => 'events', 'label' => 'Events', 'icon' => 'calendar', 'route' => 'events.index'],
                ['key' => 'calendar', 'label' => 'Calendar', 'icon' => 'calendar', 'route' => 'admin.calendar'],
                ['key' => 'applications', 'label' => 'Applications', 'icon' => 'clipboard', 'route' => 'admin.applications'],
                ['key' => 'sports', 'label' => 'Sports', 'icon' => 'trophy', 'route' => 'sports.index'],
                ['key' => 'athletes', 'label' => 'Athletes', 'icon' => 'activity', 'route' => 'athletes.index'],
                ['key' => 'medical', 'label' => 'Medical', 'icon' => 'medical', 'route' => 'admin.medical'],
                ['key' => 'coaches', 'label' => 'Coaches', 'icon' => 'users', 'route' => 'coaches.index'],
                ['key' => 'attendance', 'label' => 'Attendance', 'icon' => 'clipboard', 'route' => 'admin.attendance'],
                ['key' => 'announcements', 'label' => 'Announcements', 'icon' => 'megaphone', 'route' => 'admin.announcements'],
                ['key' => 'achievements', 'label' => 'Achievements', 'icon' => 'trophy', 'route' => 'admin.achievements'],
                ['key' => 'certificate-requests', 'label' => 'Certificate Requests', 'icon' => 'certificate', 'route' => 'admin.certificate-requests'],
                ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart', 'route' => 'reports.index'],
            ],
            'active' => 'achievements',
            'roleLabel' => 'Admin Portal',
            'userName' => auth()->user()->name,
            'userRole' => auth()->user()->role,
            'announcements' => [],
        ], $data);
    }

    private function ensureAdministrator(): void
    {
        abort_unless(auth()->user()?->role === 'Administrator', 403);
    }
}