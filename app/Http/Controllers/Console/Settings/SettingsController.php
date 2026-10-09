<?php

namespace App\Http\Controllers\Console\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Gate page – enter password to access settings.
     */
    public function gate(Request $request)
    {
        $returnUrl = $request->query('return', route('console.settings.index'));
        return view('console.settings.gate', compact('returnUrl'));
    }

    /**
     * Verify settings access password and set session.
     */
    public function verifyPassword(Request $request)
    {
        $password = config('settings.access_password', '');
        if ($password === '' || $request->input('password') !== $password) {
            return response()->json(['success' => false, 'message' => 'Incorrect password. Access denied.'], 401);
        }
        session(['settings_access_verified' => true]);
        return response()->json(['success' => true]);
    }

    /**
     * Main settings page – lists all settings sections.
     */
    public function index()
    {
        return view('console.settings.index');
    }
}
