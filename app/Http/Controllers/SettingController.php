<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingController extends Controller
{
    public function edit()
    {
        return Inertia::render('Settings/Edit', [
            'settings' => collect(Setting::values())->only(array_keys(Setting::DEFAULTS)),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:100',
            'tagline' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:255',
            'customer_id_prefix' => 'required|alpha|max:5',
            'receipt_footer' => 'nullable|string|max:255',
        ]);

        $validated['customer_id_prefix'] = strtoupper($validated['customer_id_prefix']);
        Setting::put($validated);

        return back()->with('success', 'Settings saved.');
    }
}
