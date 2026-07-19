<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $organizations = Organization::where('active', true)->orderBy('name')->get();

        $query = Event::approved()->with('organization');

        if ($slug = $request->query('organization')) {
            $query->whereHas('organization', fn ($q) => $q->where('slug', $slug));
        }

        if ($request->query('view') === 'month') {
            $raw = (string) $request->query('month');
            if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $raw)) {
                $month = Carbon::createFromFormat('Y-m-d', $raw . '-01')->startOfMonth();
            } else {
                $month = now()->startOfMonth();
            }
            $gridStart = $month->copy()->startOfWeek(Carbon::SUNDAY);
            $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

            $eventsByDay = $query->whereBetween('starts_at', [$gridStart, $gridEnd])
                ->orderBy('starts_at')->get()
                ->groupBy(fn ($e) => $e->starts_at->format('Y-m-d'));

            return view('events.month', compact('eventsByDay', 'month', 'gridStart', 'gridEnd', 'organizations'));
        }

        $eventsByDay = $query->where('starts_at', '>=', now()->startOfDay())
            ->orderBy('starts_at')->limit(100)->get()
            ->groupBy(fn ($e) => $e->starts_at->format('Y-m-d'));

        return view('events.index', compact('eventsByDay', 'organizations'));
    }

    public function ics()
    {
        $events = Event::approved()
            ->where('starts_at', '>=', now()->subDay())
            ->where('starts_at', '<=', now()->addDays(90))
            ->orderBy('starts_at')
            ->get();

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Lake City Commons//Events//EN',
            'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:Lake City Commons Events',
        ];

        foreach ($events as $event) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:event-' . $event->id . '@lakecitycommons.org';
            $lines[] = 'DTSTAMP:' . $event->updated_at->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTSTART:' . $event->starts_at->utc()->format('Ymd\THis\Z');
            if ($event->ends_at) {
                $lines[] = 'DTEND:' . $event->ends_at->utc()->format('Ymd\THis\Z');
            }
            $lines[] = 'SUMMARY:' . $this->icsEscape($event->title);
            if ($event->location) {
                $lines[] = 'LOCATION:' . $this->icsEscape($event->location);
            }
            if ($event->url) {
                $lines[] = 'URL:' . $event->url;
            }
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines) . "\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="lake-city-commons.ics"',
        ]);
    }

    private function icsEscape(string $text): string
    {
        return str_replace(["\\", ";", ",", "\n"], ["\\\\", "\\;", "\\,", "\\n"], $text);
    }
}
