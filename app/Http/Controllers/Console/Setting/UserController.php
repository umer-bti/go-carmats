<?php

namespace App\Http\Controllers\Console\Setting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Show profile and password forms.
     */
    public function showProfile()
    {
        return view('console.settings.profile.index');
    }

    public function updateProfile(Request $request, User $user)
    {
        // Validate the request data
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . Auth::id(),
        ]);
        // Update the user's profile
        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);
        // Redirect back with success message
        return back()->with('success', 'Profile updated successfully.');
    }

    public function updatePassword(Request $request, User $user)
    {
        // Validate the request data
        $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', 'confirmed'],
        ]);

        if (!Hash::check($request->current_password, $user->password)) {
            return back()->with('error', 'Current password is incorrect.');
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Redirect back with success message
        return back()->with('success', 'Password updated successfully.');
    }
}
