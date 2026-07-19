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
}
