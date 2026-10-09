<?php

namespace App\Http\Controllers\Console\Settings;

use App\Http\Controllers\Controller;
use App\Models\ShipstationSetting;
use Illuminate\Http\Request;

class ShipstationSettingController extends Controller
{
    public function index()
    {
        $accounts = ShipstationSetting::orderByDesc('id')->get();

        return view('console.settings.shipstation.index', compact('accounts'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'client_id' => 'required|string|max:500',
            'client_secret' => 'required|string|max:500',
            'is_active' => 'required|boolean',
        ]);

        ShipstationSetting::create([
            'name' => $request->input('name'),
            'client_id' => $request->input('client_id'),
            'client_secret' => $request->input('client_secret'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return response()->json(['success' => true, 'message' => 'ShipStation account added.']);
    }

    public function update(Request $request, ShipstationSetting $shipstation)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'client_id' => 'nullable|string|max:500',
            'client_secret' => 'nullable|string|max:500',
            'is_active' => 'required|boolean',
        ]);

        $data = [
            'name' => $request->input('name'),
            'client_id' => $request->input('client_id'),
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->filled('client_secret')) {
            $data['client_secret'] = $request->client_secret;
        }

        $shipstation->update($data);

        return response()->json(['success' => true, 'message' => 'ShipStation account updated.']);
    }

    public function destroy(ShipstationSetting $shipstation)
    {
        $shipstation->delete();

        return response()->json(['success' => true, 'message' => 'ShipStation account deleted.']);
    }

    public function setActive(ShipstationSetting $shipstation)
    {
        $shipstation->update(['is_active' => true]);

        return response()->json(['success' => true, 'message' => 'Account set as active.']);
    }
}
