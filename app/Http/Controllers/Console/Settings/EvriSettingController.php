<?php

namespace App\Http\Controllers\Console\Settings;

use App\Http\Controllers\Controller;
use App\Models\EvriSetting;
use Illuminate\Http\Request;

class EvriSettingController extends Controller
{
    public function index()
    {
        $accounts = EvriSetting::orderBy('id', 'desc')->orderBy('id')->get();
        return view('console.settings.evri.index', compact('accounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'api_key' => 'required|string|max:500',
            'api_secret' => 'required|string|max:500',
            'client_id' => 'required|string|max:100',
            'client_name' => 'required|string|max:255',
            'child_client_id' => 'nullable|string|max:100',
            'child_client_name' => 'nullable|string|max:255',
        ]);

        $data = $request->only([
            'api_key', 'api_secret', 'client_id', 'client_name', 'child_client_id', 'child_client_name',
        ]);
        $first = EvriSetting::count() === 0;
        $data['is_active'] = $first;

        EvriSetting::create($data);
        return response()->json(['success' => true, 'message' => 'Evri account added.']);
    }

    public function update(Request $request, EvriSetting $evri)
    {
        $request->validate([
            'api_key' => 'nullable|string|max:500',
            'api_secret' => 'nullable|string|max:500',
            'client_id' => 'nullable|string|max:100',
            'client_name' => 'nullable|string|max:255',
            'child_client_id' => 'nullable|string|max:100',
            'child_client_name' => 'nullable|string|max:255',
        ]);

        $data = [
            'client_id' => $request->input('client_id'),
            'client_name' => $request->input('client_name'),
            'child_client_id' => $request->input('child_client_id'),
            'child_client_name' => $request->input('child_client_name'),
        ];
        if ($request->filled('api_key')) {
            $data['api_key'] = $request->api_key;
        }
        if ($request->filled('api_secret')) {
            $data['api_secret'] = $request->api_secret;
        }
        $evri->update($data);
        return response()->json(['success' => true, 'message' => 'Evri account updated.']);
    }

    public function destroy(EvriSetting $evri)
    {
        $isActive = $evri->is_active;
        if ($isActive) {
            return response()->json(['success' => false, 'message' => 'Active Evri account cannot be deleted.']);
        }
        $evri->delete();
        return response()->json(['success' => true, 'message' => 'Evri account deleted.']);
    }

    public function setActive(EvriSetting $evri)
    {
        $evri->setActive();
        return response()->json(['success' => true, 'message' => 'Account set as active for label printing.']);
    }
}
