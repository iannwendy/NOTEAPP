<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserPreferenceController extends Controller
{
    /**
     * Show the form for editing the user preferences.
     */
    public function edit()
    {
        $user = Auth::user();
        $preferences = $user->preferences ?? [
            'font_size' => 'medium',
            'theme' => 'light',
            'note_color' => '#ffffff'
        ];
        
        return view('preferences.edit', compact('preferences'));
    }

    /**
     * Update the user's preferences.
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'font_size' => 'required|in:small,medium,large',
            'theme' => 'required|in:light,dark',
            'note_color' => 'required|string|max:7'
        ]);

        $user = Auth::user();
        $user->preferences = $validated;
        $user->save();

        return redirect()->route('preferences.edit')
            ->with('success', 'Preferences updated successfully.');
    }
}
