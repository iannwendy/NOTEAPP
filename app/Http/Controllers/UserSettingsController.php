<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    /**
     * Display user profile.
     */
    public function profile()
    {
        return view('user.profile', ['user' => Auth::user()]);
    }
    
    /**
     * Show the form for editing profile.
     */
    public function editProfile()
    {
        return view('user.edit-profile', ['user' => Auth::user()]);
    }
    
    /**
     * Update the user profile.
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'bio' => ['nullable', 'string', 'max:500'],
        ]);
        
        $user->update($validated);
        
        return redirect()->route('user.profile')->with('success', 'Profile updated successfully.');
    }
    
    /**
     * Show the form for changing avatar.
     */
    public function editAvatar()
    {
        return view('user.edit-avatar', ['user' => Auth::user()]);
    }
    
    /**
     * Update the user avatar.
     */
    public function updateAvatar(Request $request)
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:1024'],
        ]);
        
        $user = Auth::user();
        
        // Delete old avatar if exists
        if ($user->avatar) {
            Storage::disk('public')->delete('avatars/' . $user->avatar);
        }
        
        // Store new avatar
        $avatarName = $user->id . '_' . time() . '.' . $request->avatar->extension();
        $request->avatar->storeAs('avatars', $avatarName, 'public');
        
        // Update user record
        $user->avatar = $avatarName;
        $user->save();
        
        return redirect()->route('user.profile')->with('success', 'Avatar updated successfully.');
    }
    
    /**
     * Show the form for changing password.
     */
    public function editPassword()
    {
        return view('user.edit-password');
    }
    
    /**
     * Update the user password.
     */
    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        
        $user = Auth::user();
        $user->password = Hash::make($validated['password']);
        $user->save();
        
        return redirect()->route('user.profile')->with('success', 'Password changed successfully.');
    }
}
