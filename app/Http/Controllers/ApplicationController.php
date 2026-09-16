<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Sport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'grade' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'sport' => ['required', 'string', 'exists:sports,name'],
        ]);

        Application::create($validated);

        return redirect()->route('application.create')->with('submitted', true);
    }
}