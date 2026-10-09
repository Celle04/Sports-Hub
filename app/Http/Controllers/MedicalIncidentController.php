<?php

namespace App\Http\Controllers;

use App\Models\MedicalIncident;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Injury and medical incident tracking for the Medical module.
 *
 * Incidents are athlete-centric, so they stay attached to the athlete's
 * medical profile no matter how many medical records that athlete has.
 */
class MedicalIncidentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $this->ensureMedicalAccess();

        $validated = $this->validateIncident($request);
        $athlete = User::where('role', 'Student')->findOrFail($validated['athlete_id']);

        MedicalIncident::create($this->incidentAttributes($validated, $athlete));

        return redirect()->route('admin.medical')->with('success', 'Injury / medical incident recorded successfully.');
    }

    public function update(Request $request, MedicalIncident $incident): RedirectResponse
    {
        $this->ensureMedicalAccess();

        $validated = $this->validateIncident($request);
        $athlete = User::where('role', 'Student')->findOrFail($validated['athlete_id']);

        $incident->update($this->incidentAttributes($validated, $athlete));

        return redirect()->route('admin.medical')->with('success', 'Injury / medical incident updated successfully.');
    }

    public function destroy(MedicalIncident $incident): RedirectResponse
    {
        $this->ensureMedicalAccess();

        $incident->delete();

        return redirect()->route('admin.medical')->with('success', 'Injury / medical incident deleted.');
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function incidentAttributes(array $validated, User $athlete): array
    {
        return [
            'athlete_id' => $athlete->id,
            'sport_id' => $validated['sport_id'] ?? $athlete->sport_id,
            'incident_date' => $validated['incident_date'],
            'activity' => $validated['activity'] ?? null,
            'injury_type' => $validated['injury_type'],
            'body_part' => $validated['body_part'] ?? null,
            'severity' => $validated['severity'],
            'description' => $validated['description'],
            'treatment' => $validated['treatment'] ?? null,
            'rest_period_days' => $validated['rest_period_days'] ?? null,
            'return_to_play_date' => $validated['return_to_play_date'] ?? null,
            'medical_clearance' => $validated['medical_clearance'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateIncident(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'athlete_id' => [
                'required',
                Rule::exists('users', 'id')->where(fn ($query) => $query->where('role', 'Student')),
            ],
            'sport_id' => ['nullable', 'exists:sports,id'],
            'incident_date' => ['required', 'date'],
            'activity' => ['nullable', 'string', 'max:150'],
            'injury_type' => ['required', 'string', 'max:100'],
            'body_part' => ['nullable', 'string', 'max:100'],
            'severity' => ['required', Rule::in(MedicalIncident::SEVERITIES)],
            'description' => ['required', 'string', 'max:2000'],
            'treatment' => ['nullable', 'string', 'max:2000'],
            'rest_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'return_to_play_date' => ['nullable', 'date', 'after_or_equal:incident_date'],
            'medical_clearance' => ['required', Rule::in(MedicalIncident::CLEARANCES)],
        ], [
            'athlete_id.required' => 'Select the athlete this incident belongs to.',
            'athlete_id.exists' => 'The selected athlete no longer exists.',
            'sport_id.exists' => 'The selected sport no longer exists.',
            'incident_date.required' => 'Enter the date of the injury or incident.',
            'incident_date.date' => 'Enter a valid incident date.',
            'injury_type.required' => 'Enter the type of injury or medical incident.',
            'severity.required' => 'Select the severity of the injury.',
            'severity.in' => 'Select one of the supported severity levels.',
            'description.required' => 'Describe what happened.',
            'rest_period_days.integer' => 'The rest period must be a number of days.',
            'rest_period_days.min' => 'The rest period cannot be negative.',
            'rest_period_days.max' => 'The rest period cannot be longer than a year.',
            'return_to_play_date.date' => 'Enter a valid return-to-play date.',
            'return_to_play_date.after_or_equal' => 'The return-to-play date must be on or after the incident date.',
            'medical_clearance.required' => 'Select the medical clearance state.',
            'medical_clearance.in' => 'Select one of the supported medical clearance states.',
        ]);

        $validator->after(fn ($validator) => $this->validateSportAssignment($validator, $request));

        return $validator->validate();
    }

    /**
     * An incident may only be filed under a sport the athlete actually plays.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     */
    private function validateSportAssignment($validator, Request $request): void
    {
        $athlete = User::where('role', 'Student')->find($request->input('athlete_id'));
        $sportId = $request->input('sport_id');

        if (! $athlete || ! $sportId) {
            return;
        }

        if (! in_array((int) $sportId, $athlete->sportIds(), true)) {
            $validator->errors()->add('sport_id', 'That sport is not assigned to the selected athlete.');
        }
    }

    private function ensureMedicalAccess(): void
    {
        abort_unless(Gate::allows('view-medical-details'), 403);
    }
}
