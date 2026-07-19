<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class SubmissionController extends Controller
{
    public function create()
    {
        return view('submissions.create');
    }

    public function store(Request $request)
    {
        // Honeypot: real users never see or fill this field.
        // Checked before rate limiting so bot noise never burns a
        // shared IP's daily quota.
        if ($request->filled('website')) {
            return redirect()->route('submissions.create')
                ->with('success', 'Thanks! Your submission is in the review queue.');
        }

        $key = 'submissions:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            abort(429);
        }

        $data = $request->validate([
            'type'            => 'required|in:event,announcement',
            'submitter_name'  => 'required|string|max:120',
            'submitter_email' => 'required|email|max:255',
            'title'           => 'required|string|max:255',
            'body'            => 'required|string|max:5000',
            'starts_at'       => 'required_if:type,event|nullable|date|after:now',
            'location'        => 'nullable|string|max:255',
            'url'             => 'nullable|url:http,https|max:255',
        ]);

        Submission::create([
            'type'            => $data['type'],
            'submitter_name'  => $data['submitter_name'],
            'submitter_email' => $data['submitter_email'],
            'title'           => $data['title'],
            'body'            => $data['body'],
            'event_fields'    => $data['type'] === 'event' ? [
                'starts_at' => $data['starts_at'],
                'location'  => $data['location'] ?? null,
                'url'       => $data['url'] ?? null,
            ] : null,
            'status'          => 'pending',
            'ip_hash'         => hash('sha256', (string) $request->ip()),
        ]);

        RateLimiter::hit($key, 86400); // count only stored submissions, 24h window

        return redirect()->route('submissions.create')
            ->with('success', 'Thanks! Your submission is in the review queue.');
    }
}
