<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Hľadania zákazníkov na webe a v appke (titi_search_log) a porovnanie s AI agentom,
 * ktorý beží v tieni (TITI_AI_SEARCH_RESCUE=shadow): čo web nenašiel, čo by agent
 * zachránil, kde sa výsledky líšia, kliky/košík podľa zdroja (titi_search_event)
 * a náklady agenta. channel: results = stránka s výsledkami, suggest = našepkávač
 * (výber produktu/kategórie priamo v ňom, alebo nič nenašiel a zákazník odišiel).
 */
class VyhladavanieController extends Controller
{
    use ResolvesPeriod;

    private const LIVE_LIMIT = 30;

    public function index(Request $request)
    {
        [$dateFrom, $dateTo, $prevDateFrom, $prevDateTo] = $this->resolvePeriod($request);

        $base = DB::table('titi_search_log')->whereBetween('created_at', [$dateFrom, $dateTo]);
        $prevBase = DB::table('titi_search_log')->whereBetween('created_at', [$prevDateFrom, $prevDateTo]);

        $total = (clone $base)->count();
        $suggest = (clone $base)->where('channel', 'suggest')->count();
        $zero = (clone $base)->where('results_count', 0)->count();
        $prevTotal = (clone $prevBase)->count();
        $prevZero = (clone $prevBase)->where('results_count', 0)->count();

        $ai = (clone $base)->whereNotNull('ai_count')->selectRaw('
            COUNT(*) as checked,
            SUM(results_count = 0 AND ai_count > 0) as rescuable,
            SUM(results_count = 0 AND ai_count = 0) as nobody,
            SUM(ai_shown > 0) as shown,
            SUM(ai_cost) as cost,
            AVG(ai_ms) as avg_ms,
            AVG(ai_cached) as cache_rate
        ')->first();
        $aiErrors = (clone $base)->where('ai_error', 1)->count();

        $dailyTrend = (clone $base)
            ->selectRaw('DATE(created_at) as den, COUNT(*) as hladani, SUM(results_count = 0) as prazdnych')
            ->groupBy('den')
            ->orderBy('den')
            ->get()
            ->map(fn ($r) => ['den' => $r->den, 'hladani' => (int) $r->hladani, 'prazdnych' => (int) $r->prazdnych])
            ->values();

        $topQueries = (clone $base)
            ->selectRaw('query, COUNT(*) as pocet, ROUND(AVG(results_count)) as vysledkov, MAX(ai_count) as ai_count, MAX(id) as last_id')
            ->groupBy('query')
            ->orderByDesc('pocet')
            ->limit(25)
            ->get();

        $topZero = (clone $base)
            ->where('results_count', 0)
            ->selectRaw('query, COUNT(*) as pocet, MAX(ai_count) as ai_count, MAX(id) as last_id')
            ->groupBy('query')
            ->orderByDesc('pocet')
            ->limit(25)
            ->get();

        return Inertia::render('Vyhladavanie/Index', [
            'syncCost' => $this->syncCost($dateFrom, $dateTo),
            'stats' => [
                'total' => $total,
                'suggest' => $suggest,
                'zero' => $zero,
                'zeroRate' => $total ? round($zero / $total * 100, 1) : null,
                'aiChecked' => (int) ($ai->checked ?? 0),
                'rescuable' => (int) ($ai->rescuable ?? 0),
                'nobody' => (int) ($ai->nobody ?? 0), // nenašiel ani web, ani AI
                'shown' => (int) ($ai->shown ?? 0),
                'aiCost' => round((float) ($ai->cost ?? 0), 4),
                'aiAvgMs' => $ai->avg_ms !== null ? (int) round($ai->avg_ms) : null,
                'aiCacheRate' => $ai->cache_rate !== null ? round($ai->cache_rate * 100) : null,
                'aiErrors' => $aiErrors,
            ],
            'trends' => [
                'total' => $this->trend($total, $prevTotal),
                'zero' => $this->trend($zero, $prevZero),
            ],
            'events' => $this->eventsBySource($dateFrom, $dateTo),
            'dailyTrend' => $dailyTrend,
            'topQueries' => $topQueries,
            'topZero' => $topZero,
            'differences' => $this->differences(clone $base),
            'live' => $this->latest(0),
            'obdobie' => [
                'date_from' => substr($dateFrom, 0, 10),
                'date_to' => substr($dateTo, 0, 10),
            ],
        ]);
    }

    /** Nové hľadania od posledného ID (polling stránky) + dnešné počítadlá. */
    public function live(Request $request)
    {
        $today = DB::table('titi_search_log')->where('created_at', '>=', now()->startOfDay())->selectRaw('
            COUNT(*) as hladani,
            SUM(results_count = 0) as prazdnych,
            SUM(results_count = 0 AND ai_count > 0) as zachranitelnych,
            SUM(ai_cost) as naklady
        ')->first();

        return response()->json([
            'rows' => $this->latest((int) $request->input('since_id', 0)),
            'today' => [
                'hladani' => (int) ($today->hladani ?? 0),
                'prazdnych' => (int) ($today->prazdnych ?? 0),
                'zachranitelnych' => (int) ($today->zachranitelnych ?? 0),
                'naklady' => round((float) ($today->naklady ?? 0), 4),
            ],
        ]);
    }

    /** Čo pri jednom hľadaní vrátil web (top 10) a čo agent + kliky/košík. */
    public function porovnanie(int $id)
    {
        $log = DB::table('titi_search_log')->where('id', $id)->first();
        abort_if(! $log, 404);

        $webIds = $this->ids($log->web_product_ids);
        $aiIds = $this->ids($log->ai_product_ids);
        $names = DB::table('titi_product_description')
            ->whereIn('product_id', array_merge($webIds, $aiIds) ?: [0])
            ->pluck('name', 'product_id');

        $events = DB::table('titi_search_event')->where('search_log_id', $id)
            ->get(['product_id', 'source', 'action']);

        $toProducts = fn (array $ids, array $other) => array_map(fn ($pid) => [
            'product_id' => $pid,
            'name' => $names[$pid] ?? "#{$pid}",
            'v_druhom' => in_array($pid, $other, true), // našiel ho aj druhý zdroj
            'kliky' => $events->where('product_id', $pid)->where('action', 'click')->count(),
            'kosik' => $events->where('product_id', $pid)->where('action', 'cart')->count(),
        ], $ids);

        return response()->json([
            'query' => $log->query,
            'results_count' => $log->results_count,
            'web' => $toProducts($webIds, $aiIds),
            'ai' => $toProducts($aiIds, $webIds),
            'ai_ms' => $log->ai_ms,
            'ai_cached' => (bool) $log->ai_cached,
        ]);
    }

    private function latest(int $sinceId): array
    {
        return DB::table('titi_search_log')
            ->when($sinceId > 0, fn ($q) => $q->where('id', '>', $sinceId))
            ->orderByDesc('id')
            ->limit(self::LIVE_LIMIT)
            ->get(['id', 'query', 'results_count', 'channel', 'ai_count', 'ai_shown', 'ai_ms', 'ai_error', 'created_at'])
            ->all();
    }

    /**
     * Kliky a pridania do košíka z výsledkov hľadania podľa zdroja (web / ai) a výbery
     * v našepkávači (suggest: click = produkt, category = kategória).
     */
    private function eventsBySource(string $dateFrom, string $dateTo): array
    {
        $rows = DB::table('titi_search_event as e')
            ->join('titi_search_log as l', 'l.id', '=', 'e.search_log_id')
            ->whereBetween('l.created_at', [$dateFrom, $dateTo])
            ->selectRaw('e.source, e.action, COUNT(*) as pocet, COUNT(DISTINCT e.search_log_id) as hladani')
            ->groupBy('e.source', 'e.action')
            ->get();

        $out = [];
        foreach (['web' => ['click', 'cart'], 'ai' => ['click', 'cart'], 'suggest' => ['click', 'category']] as $source => $actions) {
            foreach ($actions as $action) {
                $row = $rows->first(fn ($r) => $r->source === $source && $r->action === $action);
                $out[$source][$action] = (int) ($row->pocet ?? 0);
                $out[$source][$action.'_hladani'] = (int) ($row->hladani ?? 0);
            }
        }

        return $out;
    }

    /**
     * Hľadania, kde web aj agent niečo našli, s najmenšou zhodou: koľko produktov
     * agenta je aj v top 10 webu. Nízka zhoda = kandidát na opravu radenia webu
     * (napr. „papier a4 biely“ → krabice na tortu, agent kopírovací papier).
     */
    private function differences($base): array
    {
        $rows = $base->where('results_count', '>', 0)->where('ai_count', '>', 0)
            ->whereNotNull('web_product_ids')
            ->orderByDesc('id')
            ->limit(2000)
            ->get(['id', 'query', 'results_count', 'web_product_ids', 'ai_product_ids']);

        $byQuery = [];
        foreach ($rows as $row) {
            $byQuery[$row->query] ??= ['row' => $row, 'pocet' => 0]; // najnovší riadok dotazu
            $byQuery[$row->query]['pocet']++;
        }

        $out = [];
        foreach ($byQuery as $query => ['row' => $row, 'pocet' => $pocet]) {
            $web = $this->ids($row->web_product_ids);
            $aiIds = $this->ids($row->ai_product_ids);
            $common = count(array_intersect($web, $aiIds));
            $out[] = [
                'query' => $query,
                'pocet' => $pocet,
                'vysledkov' => (int) $row->results_count,
                'ai_count' => count($aiIds),
                'spolocnych' => $common,
                'zhoda' => (int) round($common / count($aiIds) * 100),
                'last_id' => $row->id,
            ];
        }

        usort($out, fn ($a, $b) => [$a['zhoda'], -$a['pocet']] <=> [$b['zhoda'], -$b['pocet']]);

        return array_slice($out, 0, 25);
    }

    /**
     * Náklady OpenAI na nočnú aktualizáciu produktov a kategórií (embeddingy) za obdobie
     * z titi_ai_usage_log (zapisuje agent pri synchronizácii). Null, ak tabuľka ešte nie je.
     */
    private function syncCost(string $dateFrom, string $dateTo): ?array
    {
        try {
            $row = DB::table('titi_ai_usage_log')
                ->whereBetween('created_at', [$dateFrom, $dateTo])
                ->selectRaw('COUNT(*) as behov, SUM(items) as poloziek, SUM(cost_usd) as naklady')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }

        return [
            'behov' => (int) ($row->behov ?? 0),
            'poloziek' => (int) ($row->poloziek ?? 0),
            'naklady' => round((float) ($row->naklady ?? 0), 4),
        ];
    }

    /** @return array<int, int> */
    private function ids(?string $csv): array
    {
        return array_values(array_map('intval', array_filter(explode(',', (string) $csv))));
    }
}
