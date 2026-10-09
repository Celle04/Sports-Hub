<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesProfilePhotos;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    use HandlesProfilePhotos;

    /**
     * Emergency contact fields, grouped so the athlete and admin forms can
     * validate them as one unit.
     *
     * @var array<int, string>
     */
    public const EMERGENCY_CONTACT_FIELDS = [
        'emergency_contact_name',
        'emergency_contact_relationship',
        'emergency_contact_phone',
    ];

    /**
     * Update the personal information the athlete is allowed to change.
     * Role, sport assignment and student ID stay under admin control.
     */
    public function update(Request $request): RedirectResponse
    {
        $this->ensureAthlete();

        $athlete = $request->user();
        $validated = $request->validate(
            array_merge($this->personalRules($athlete), $this->photoRules()),
            array_merge([
                'email.unique' => 'The email has already been taken.',
                'emergency_contact_name.required' => 'Enter the name of the person to contact in an emergency.',
                'emergency_contact_phone.required' => 'Enter a contact number for the emergency contact.',
            ], $this->photoMessages()),
        );

        $athlete->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'grade_level' => $validated['grade_level'] ?? null,
            'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
            'emergency_contact_relationship' => $validated['emergency_contact_relationship'] ?? null,
            'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
        ]);

        $this->syncProfilePhoto($request, $athlete);

        return back()->with('success', 'Profile updated successfully.');
    }

/**
 * Fields the athlete is allowed to change themselves.
 *
 * Role, sport assignment and student ID stay under admin control.
 *
 * @return array<string, array<int, mixed>>
 */
private function personalRules(User $athlete): array
{
    // A half filled emergency contact is worse than none, so any part of it
    // makes the name and the number mandatory.
    $hasEmergencyContact = collect(self::EMERGENCY_CONTACT_FIELDS)
        ->contains(fn (string $field) => request()->filled($field));

    return [
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($athlete->id)],
        'phone' => ['nullable', 'string', 'max:50'],
        'grade_level' => ['nullable', 'string', 'max:100'],
        'emergency_contact_name' => [$hasEmergencyContact ? 'required' : 'nullable', 'string', 'max:255'],
        'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
        'emergency_contact_phone' => [$hasEmergencyContact ? 'required' : 'nullable', 'string', 'max:50'],
    ];
}

/**
 * Change the athlete's own password after verifying the current one.
 */
    public function updatePassword(Request $request): RedirectResponse
    {
        $this->ensureAthlete();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'current_password.current_password' => 'The current password is incorrect.',
            'password.confirmed' => 'The new passwords do not match.',
        ]);

        $athlete = $request->user();
        $athlete->forceFill([
            'password' => $validated['password'],
        ])->setRememberToken(Str::random(60));
        $athlete->save();

        $request->session()->regenerate();

        return back()->with('success', 'Your password has been changed successfully.');
    }

    /**
     * Only the athlete may edit their own profile.
     */
    private function ensureAthlete(): void
    {
        abort_unless(request()->user()?->role === 'Student', 403);
    }
}