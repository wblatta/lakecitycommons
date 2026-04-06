<?php

namespace App\Services;

use App\Models\AvailabilitySchedule;
use App\Models\ExchangeRequest;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;

class AvailabilityService
{
    public function getAvailabilityForResource(Model $resource, int $weeks = 4): array
    {
        $schedules = AvailabilitySchedule::where('resource_type', get_class($resource))
            ->where('resource_id', $resource->id)
            ->get();

        $grid = [];
        $start = Carbon::today();
        $end = $start->copy()->addWeeks($weeks);

        foreach (CarbonPeriod::create($start, $end) as $date) {
            $dateStr = $date->toDateString();
            $dow = (int) $date->dayOfWeek;

            // Check specific block first
            $block = $schedules->first(fn($s) => $s->recurrence === 'specific'
                && $s->specific_date->toDateString() === $dateStr
                && $s->is_blocked);

            if ($block) {
                $grid[$dateStr] = ['available' => false, 'blocked' => true, 'windows' => []];
                continue;
            }

            // Check specific open override
            $specificOpen = $schedules->filter(fn($s) => $s->recurrence === 'specific'
                && $s->specific_date->toDateString() === $dateStr
                && !$s->is_blocked);

            if ($specificOpen->isNotEmpty()) {
                $grid[$dateStr] = [
                    'available' => true,
                    'blocked' => false,
                    'windows' => $specificOpen->map(fn($s) => ['start' => $s->start_time, 'end' => $s->end_time])->values()->toArray(),
                ];
                continue;
            }

            // Weekly recurring
            $weekly = $schedules->filter(fn($s) => $s->recurrence === 'weekly' && $s->day_of_week === $dow && !$s->is_blocked);

            $grid[$dateStr] = [
                'available' => $weekly->isNotEmpty(),
                'blocked' => false,
                'windows' => $weekly->map(fn($s) => ['start' => $s->start_time, 'end' => $s->end_time])->values()->toArray(),
            ];
        }

        return $grid;
    }

    public function isAvailable(Model $resource, Carbon $datetime, float $durationHours = 1.0): bool
    {
        $grid = $this->getAvailabilityForResource($resource, 8);
        $dateStr = $datetime->toDateString();

        if (!isset($grid[$dateStr]) || !$grid[$dateStr]['available']) {
            return false;
        }

        foreach ($grid[$dateStr]['windows'] as $window) {
            $windowStart = Carbon::parse($dateStr . ' ' . $window['start']);
            $windowEnd = Carbon::parse($dateStr . ' ' . $window['end']);
            $requestEnd = $datetime->copy()->addHours($durationHours);

            if ($datetime->gte($windowStart) && $requestEnd->lte($windowEnd)) {
                // Check no conflicting active request
                $conflict = ExchangeRequest::where('resource_type', get_class($resource))
                    ->where('resource_id', $resource->id)
                    ->whereIn('status', ['accepted', 'in_progress'])
                    ->where(function ($q) use ($datetime, $requestEnd) {
                        $q->whereBetween('proposed_datetime', [$datetime, $requestEnd])
                          ->orWhere(function ($q2) use ($datetime, $requestEnd) {
                              $q2->where('proposed_datetime', '<=', $datetime)
                                 ->whereRaw('DATE_ADD(proposed_datetime, INTERVAL duration_hours HOUR) >= ?', [$requestEnd]);
                          });
                    })
                    ->exists();

                if (!$conflict) {
                    return true;
                }
            }
        }

        return false;
    }
}
