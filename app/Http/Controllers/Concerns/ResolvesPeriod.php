<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

/**
 * Obdobie z ?date_from/?date_to (predvolene posledných 30 dní) a rovnako dlhé
 * predchádzajúce obdobie na porovnanie trendu.
 */
trait ResolvesPeriod
{
    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    protected function resolvePeriod(Request $request): array
    {
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $dateFrom = $request->filled('date_from')
                ? $request->date_from.' 00:00:00'
                : '2000-01-01 00:00:00';
            $dateTo = $request->filled('date_to')
                ? $request->date_to.' 23:59:59'
                : now()->endOfDay()->toDateTimeString();
        } else {
            $dateFrom = now()->subDays(29)->startOfDay()->toDateTimeString();
            $dateTo = now()->endOfDay()->toDateTimeString();
        }

        $dtFrom = new \DateTime($dateFrom);
        $dtTo = new \DateTime($dateTo);
        $durationDays = (int) $dtFrom->diff($dtTo)->days;

        $prevTo = (clone $dtFrom)->modify('-1 second');
        $prevFrom = (clone $prevTo)->modify("-{$durationDays} days")->setTime(0, 0, 0);

        return [
            $dateFrom,
            $dateTo,
            $prevFrom->format('Y-m-d H:i:s'),
            $prevTo->format('Y-m-d H:i:s'),
        ];
    }

    protected function trend(int $current, int $prev): ?float
    {
        if ($prev === 0) {
            return null;
        }

        return round((($current - $prev) / $prev) * 100, 1);
    }
}
