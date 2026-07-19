<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use App\Models\Event;
use App\Models\Source;
use App\Models\Submission;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index()
    {
        $submissions = Submission::where('status', 'pending')->latest()->get();
        $pendingEvents = Event::where('status', 'pending')->orderBy('starts_at')->get();
        $failingSources = Source::active()->where('consecutive_failures', '>=', 2)->get();
        $digestDraft = \App\Models\Post::whereIn('status', ['draft', 'review'])
            ->where('title', 'like', \App\Console\Commands\DraftDigest::TITLE_PREFIX . '%')
            ->latest()->first();

        return view('admin.review.index', compact('submissions', 'pendingEvents', 'failingSources', 'digestDraft'));
    }

    public function approveSubmission(Request $request, Submission $submission)
    {
        abort_unless($submission->status === 'pending', 404);

        if ($submission->type === 'event') {
            $startsAt = $submission->event_fields['starts_at'] ?? null;
            if (! $startsAt) {
                return back()->with('error', 'This event submission has no date — reject it instead.');
            }

            Event::create([
                'title'         => $submission->title,
                'description'   => $submission->body,
                'starts_at'     => $startsAt,
                'location'      => $submission->event_fields['location'] ?? null,
                'url'           => $submission->event_fields['url'] ?? null,
                'submission_id' => $submission->id,
                'status'        => 'approved',
            ]);
        }

        $submission->update(['status' => 'approved']);
        $this->log($request, 'submission_approve', ['submission_id' => $submission->id, 'title' => $submission->title]);

        return back()->with('success', 'Submission approved.');
    }

    public function rejectSubmission(Request $request, Submission $submission)
    {
        abort_unless($submission->status === 'pending', 404);

        $submission->update(['status' => 'rejected']);
        $this->log($request, 'submission_reject', ['submission_id' => $submission->id, 'title' => $submission->title]);

        return back()->with('success', 'Submission rejected.');
    }

    public function approveEvent(Request $request, Event $event)
    {
        abort_unless($event->status === 'pending', 404);

        $event->update(['status' => 'approved']);
        $this->log($request, 'event_approve', ['event_id' => $event->id, 'title' => $event->title]);

        return back()->with('success', 'Event approved.');
    }

    public function rejectEvent(Request $request, Event $event)
    {
        abort_unless($event->status === 'pending', 404);

        $event->update(['status' => 'rejected']);
        $this->log($request, 'event_reject', ['event_id' => $event->id, 'title' => $event->title]);

        return back()->with('success', 'Event rejected.');
    }

    private function log(Request $request, string $action, array $payload): void
    {
        AdminAuditLog::create([
            'admin_id'   => $request->user()->id,
            'action'     => $action,
            'payload'    => $payload,
            'ip_address' => $request->ip(),
        ]);
    }
}
