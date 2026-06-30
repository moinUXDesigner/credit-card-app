<?php

namespace App\Services;

use Carbon\Carbon;

class DateOccurrenceService
{
    /**
     * Next occurrence of a recurring day-of-month on or after $now,
     * with the day clamped to the shorter month when it doesn't exist (e.g. 31st in Feb).
     */
    public function nextOccurrence(int $dayOfMonth, ?Carbon $now = null): array
    {
        $today = ($now ?? Carbon::now())->copy()->startOfDay();
        $target = Carbon::create($today->year, $today->month, min($dayOfMonth, $today->daysInMonth));

        if ($target->lessThan($today)) {
            $target->addMonthNoOverflow();
            $target->day(min($dayOfMonth, $target->daysInMonth));
        }

        return [
            'date' => $target->toDateString(),
            'days_until' => $today->diffInDays($target),
        ];
    }
}
