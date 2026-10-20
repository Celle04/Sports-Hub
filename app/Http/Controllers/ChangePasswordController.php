<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Lets an athlete whose account was marked for a mandatory password change
 * (for example after an administrator-assisted reset) choose a new password.
 *
 * The athlete must supply their current (temporary) password before the new
 * one is accepted, and the new password must differ from it.
 */
class ChangePasswordController extends Controller
{
    public function show(): View
    {
        $this->ensureAthlete();

        return view('auth.change-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $this->ensureAthlete();

        $athlete = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
        ], [
            'current_password.current_password' => 'The temporary password is incorrect.',
            'password.confirmed' => 'The new passwords do not match.',
            'password.different' => 'The new password must be different from the temporary password.',
        ]);

        $athlete->forceFill([
            'password' => $validated['password'],
        ])->setRememberToken(Str::random(60));
        $athlete->clearPasswordChangeRequirement();
        $athlete->save();

        $request->session()->regenerate();

        return redirect()->route('student.dashboard')
            ->with('success', 'Your password has been changed successfully. You can now use it to log in.');
    }

    private function ensureAthlete(): void
    {
        abort_unless(request()->user()?->role === 'Student', 403);
    }
}
