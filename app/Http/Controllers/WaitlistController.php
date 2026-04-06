<?php

namespace App\Http\Controllers;

use App\Models\WaitlistEntry;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'resource_type' => 'required|in:skill,item',
            'resource_id'   => 'required|integer',
        ]);

        WaitlistEntry::firstOrCreate([
            'user_id'       => $request->user()->id,
            'resource_type' => $request->resource_type,
            'resource_id'   => $request->resource_id,
        ]);

        return back()->with('success', "You're on the waitlist. We'll notify you when it becomes available.");
    }

    public function destroy(WaitlistEntry $entry)
    {
        abort_unless($entry->user_id === auth()->id(), 403);
        $entry->delete();
        return back()->with('success', 'Removed from waitlist.');
    }
}
