<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

/**
 * Oplatí sa AI vo vyhľadávaní? Náklady agenta (záchrana + nočná aktualizácia produktov)
 * oproti zisku z objednávok produktov, ku ktorým zákazník prišiel z výsledkov AI.
 *
 * E-shop priraďuje košík a objednávku k hľadaniu podľa anonymného ID návštevníka
 * (SearchAttributionService): titi_search_order = objednané produkty s kliknutím
 * z hľadania (posledný klik do 7 dní), titi_visitor_order = všetky objednávky
 * známeho návštevníka (kontrolná skupina TITI_AI_HOLDOUT, stĺpec ai_holdout).
 * Hľadanie, klik aj objednávka sa počítajú k obdobiu hľadania.
 */
class AiVyhladavanieController extends Controller
{
    use ResolvesPeriod;

    /** Platba kartou nezaplatená alebo zlyhala (BesteronPaymentService) – nie je to predaj. */
    private const UNPAID_STATUS = 1;

    private const HOLDOUT_WINDOW_DAYS = 7;

    private const MONTHS = 6;

    /** Koľkokrát musí AI niečo ukázať bez jediného kliku, aby šlo o „nezmysel“. */
    private const USELESS_MIN_SHOWN = 3;

    public function index(Request $request)
    {
        [$dateFrom, $dateTo] = $this->resolvePeriod($request);
        $obdobie = ['date_from' => substr($dateFrom, 0, 10), 'date_to' => substr($dateTo, 0, 10)];

        // Pred nasadením e-shopu (SQL na produkcii) tabuľky ešte nie sú.
        if (! Schema::hasTable('titi_search_order') || ! Schema::hasColumn('titi_search_log', 'visitor_id')) {
            return Inertia::render('Vyhladavanie/Ai', ['ready' => false, 'obdobie' => $obdobie]);
        }

        $rate = (float) config('analytics.usd_eur');
        $funnel = $this->funnel($dateFrom, $dateTo);
        $searchCost = (float) DB::table('titi_search_log')->whereBetween('created_at', [$dateFrom, $dateTo])->sum('ai_cost');
        $syncCost = $this->syncCostByMonth($dateFrom, $dateTo)->sum();

        return Inertia::render('Vyhladavanie/Ai', [
            'ready' => true,
            'obdobie' => $obdobie,
            'usdEur' => $rate,
            'naklady' => [
                'vyhladavanie' => round($searchCost * $rate, 2),
                'aktualizacia' => round($syncCost * $rate, 2),
                'spolu' => round(($searchCost + $syncCost) * $rate, 2),
            ],
            'funnel' => $funnel,
            'holdout' => $this->holdout($dateFrom, $dateTo),
            'mesiace' => $this->months($dateTo, $rate),
            'zarabajuce' => $this->earningQueries($dateFrom, $dateTo),
            'nezmysly' => $this->uselessQueries($dateFrom, $dateTo),
        ]);
    }

    /**
     * Lievik AI vs. web: koľko hľadaní ukázalo výsledky, z koľkých zákazník otvoril produkt,
     * pridal do košíka a objednal + tržba a zisk (bez DPH). Web = stránka výsledkov aj našepkávač.
     */
    private function funnel(string $dateFrom, string $dateTo): array
    {
        $shown = DB::table('titi_search_log')->whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('SUM(ai_shown > 0) as ai, SUM(results_count > 0) as web')
            ->first();

        $events = DB::table('titi_search_event as e')
            ->join('titi_search_log as l', 'l.id', '=', 'e.search_log_id')
            ->whereBetween('l.created_at', [$dateFrom, $dateTo])
            ->whereIn('e.action', ['click', 'cart'])
            ->selectRaw("CASE WHEN e.source = 'ai' THEN 'ai' ELSE 'web' END as zdroj, e.action, COUNT(DISTINCT e.search_log_id) as hladani")
            ->groupBy('zdroj', 'e.action')
            ->get();

        $orders = $this->paidSearchOrders($dateFrom, $dateTo)
            ->selectRaw("CASE WHEN so.source = 'ai' THEN 'ai' ELSE 'web' END as zdroj,
                COUNT(DISTINCT so.search_log_id) as hladani, COUNT(DISTINCT so.order_id) as objednavok,
                SUM(so.quantity) as kusov, SUM(so.total) as trzba, SUM(so.zisk) as zisk")
            ->groupBy('zdroj')
            ->get()
            ->keyBy('zdroj');

        $out = [];
        foreach (['ai', 'web'] as $zdroj) {
            $event = fn (string $action) => (int) ($events->first(fn ($r) => $r->zdroj === $zdroj && $r->action === $action)->hladani ?? 0);
            $order = $orders[$zdroj] ?? null;
            $out[$zdroj] = [
                'zobrazene' => (int) ($shown->{$zdroj} ?? 0),
                'klik' => $event('click'),
                'kosik' => $event('cart'),
                'objednane' => (int) ($order->hladani ?? 0),
                'objednavok' => (int) ($order->objednavok ?? 0),
                'kusov' => (int) ($order->kusov ?? 0),
                'trzba' => round((float) ($order->trzba ?? 0), 2),
                'zisk' => round((float) ($order->zisk ?? 0), 2),
            ];
        }

        return $out;
    }

    /** titi_search_order s hľadaním v období, len zaplatené objednávky mimo interných účtov. */
    private function paidSearchOrders(string $dateFrom, string $dateTo)
    {
        return DB::table('titi_search_order as so')
            ->join('titi_search_log as l', 'l.id', '=', 'so.search_log_id')
            ->join('titi_order as o', 'o.order_id', '=', 'so.order_id')
            ->whereBetween('l.created_at', [$dateFrom, $dateTo])
            ->where('o.order_status_id', '!=', self::UNPAID_STATUS)
            ->whereNotIn('o.customer_id', config('analytics.excluded_customer_ids', []));
    }

    /**
     * Kontrolná skupina: návštevníci s prázdnym hľadaním s AI vs. bez AI (holdout) – koľkí
     * do 7 dní od prvého prázdneho hľadania objednali čokoľvek a aký zisk priniesli.
     * Null, kým holdout nebežal (ai_holdout je všade null).
     */
    private function holdout(string $dateFrom, string $dateTo): ?array
    {
        $visitors = DB::table('titi_search_log')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('results_count', 0)
            ->whereNotNull('ai_holdout')
            ->whereNotNull('visitor_id')
            ->selectRaw('visitor_id, MAX(ai_holdout) as holdout, MIN(created_at) as prve')
            ->groupBy('visitor_id')
            ->get();

        if ($visitors->isEmpty()) {
            return null;
        }

        $orders = $visitors->pluck('visitor_id')->chunk(1000)->flatMap(fn ($chunk) => DB::table('titi_visitor_order as vo')
            ->join('titi_order as o', 'o.order_id', '=', 'vo.order_id')
            ->leftJoin('titi_order_product as op', 'op.order_id', '=', 'vo.order_id')
            ->whereIn('vo.visitor_id', $chunk->all())
            ->where('o.order_status_id', '!=', self::UNPAID_STATUS)
            ->whereNotIn('o.customer_id', config('analytics.excluded_customer_ids', []))
            ->selectRaw('vo.visitor_id, vo.order_id, vo.created_at, COALESCE(SUM(op.zisk_bdph), 0) as zisk')
            ->groupBy('vo.visitor_id', 'vo.order_id', 'vo.created_at')
            ->get()
        )->groupBy('visitor_id');

        $groups = ['ai' => ['navstevnikov' => 0, 'objednali' => 0, 'zisk' => 0.0], 'bez_ai' => ['navstevnikov' => 0, 'objednali' => 0, 'zisk' => 0.0]];
        foreach ($visitors as $visitor) {
            $group = (int) $visitor->holdout === 1 ? 'bez_ai' : 'ai';
            $until = date('Y-m-d H:i:s', strtotime($visitor->prve.' +'.self::HOLDOUT_WINDOW_DAYS.' days'));
            $after = collect($orders[$visitor->visitor_id] ?? [])
                ->filter(fn ($o) => $o->created_at >= $visitor->prve && $o->created_at <= $until);

            $groups[$group]['navstevnikov']++;
            $groups[$group]['objednali'] += $after->isNotEmpty() ? 1 : 0;
            $groups[$group]['zisk'] += (float) $after->sum('zisk');
        }

        foreach ($groups as &$g) {
            $g['konverzia'] = $g['navstevnikov'] ? round($g['objednali'] / $g['navstevnikov'] * 100, 1) : null;
            $g['zisk_na_navstevnika'] = $g['navstevnikov'] ? round($g['zisk'] / $g['navstevnikov'], 2) : null;
            $g['zisk'] = round($g['zisk'], 2);
        }

        return $groups;
    }

    /** Posledných 6 mesiacov po mesiacoch (podľa konca obdobia): náklady AI, zobrazenia, kliky, zisk. */
    private function months(string $dateTo, float $rate): array
    {
        $from = date('Y-m-01 00:00:00', strtotime(substr($dateTo, 0, 7).'-01 -'.(self::MONTHS - 1).' months'));

        $logs = DB::table('titi_search_log')->whereBetween('created_at', [$from, $dateTo])
            ->selectRaw('SUBSTR(created_at, 1, 7) as mesiac, SUM(ai_cost) as naklady, SUM(ai_shown > 0) as zobrazene')
            ->groupBy('mesiac')->get()->keyBy('mesiac');

        $clicks = DB::table('titi_search_event as e')
            ->join('titi_search_log as l', 'l.id', '=', 'e.search_log_id')
            ->whereBetween('l.created_at', [$from, $dateTo])
            ->where('e.source', 'ai')->where('e.action', 'click')
            ->selectRaw('SUBSTR(l.created_at, 1, 7) as mesiac, COUNT(DISTINCT e.search_log_id) as hladani')
            ->groupBy('mesiac')->pluck('hladani', 'mesiac');

        $orders = $this->paidSearchOrders($from, $dateTo)->where('so.source', 'ai')
            ->selectRaw('SUBSTR(l.created_at, 1, 7) as mesiac, COUNT(DISTINCT so.order_id) as objednavok, SUM(so.zisk) as zisk')
            ->groupBy('mesiac')->get()->keyBy('mesiac');

        $sync = $this->syncCostByMonth($from, $dateTo);

        $out = [];
        for ($m = strtotime($from); $m <= strtotime($dateTo); $m = strtotime('+1 month', $m)) {
            $key = date('Y-m', $m);
            $naklady = ((float) ($logs[$key]->naklady ?? 0) + (float) ($sync[$key] ?? 0)) * $rate;
            $zisk = (float) ($orders[$key]->zisk ?? 0);
            $out[] = [
                'mesiac' => $key,
                'naklady' => round($naklady, 2),
                'zobrazene' => (int) ($logs[$key]->zobrazene ?? 0),
                's_klikom' => (int) ($clicks[$key] ?? 0),
                'objednavok' => (int) ($orders[$key]->objednavok ?? 0),
                'zisk' => round($zisk, 2),
                'bilancia' => round($zisk - $naklady, 2),
            ];
        }

        return array_reverse($out);
    }

    /** Náklady nočnej aktualizácie produktov (USD) po mesiacoch; prázdne bez tabuľky. */
    private function syncCostByMonth(string $dateFrom, string $dateTo)
    {
        try {
            return DB::table('titi_ai_usage_log')->whereBetween('created_at', [$dateFrom, $dateTo])
                ->selectRaw('SUBSTR(created_at, 1, 7) as mesiac, SUM(cost_usd) as naklady')
                ->groupBy('mesiac')->pluck('naklady', 'mesiac')->map(fn ($v) => (float) $v);
        } catch (\Throwable $e) {
            return collect();
        }
    }

    /** Hľadania, pri ktorých produkty od AI viedli k objednávke – podľa zisku. */
    private function earningQueries(string $dateFrom, string $dateTo): array
    {
        return $this->paidSearchOrders($dateFrom, $dateTo)->where('so.source', 'ai')
            ->selectRaw('l.query, COUNT(DISTINCT so.order_id) as objednavok, SUM(so.total) as trzba, SUM(so.zisk) as zisk')
            ->groupBy('l.query')
            ->orderByDesc('zisk')
            ->limit(25)
            ->get()
            ->map(fn ($r) => [
                'query' => $r->query,
                'objednavok' => (int) $r->objednavok,
                'trzba' => round((float) $r->trzba, 2),
                'zisk' => round((float) $r->zisk, 2),
            ])
            ->all();
    }

    /**
     * Hľadania, kde AI produkty ukázalo aspoň 3× a nikto na ne neklikol – kandidáti na
     * nezmysly (alebo zlé poradie), ktoré stoja peniaze a nič neprinášajú.
     */
    private function uselessQueries(string $dateFrom, string $dateTo): array
    {
        return DB::table('titi_search_log as l')
            ->leftJoin('titi_search_event as e', function ($join) {
                $join->on('e.search_log_id', '=', 'l.id')->where('e.source', 'ai')->where('e.action', 'click');
            })
            ->whereBetween('l.created_at', [$dateFrom, $dateTo])
            ->where('l.ai_shown', '>', 0)
            ->selectRaw('l.query, COUNT(DISTINCT l.id) as zobrazene, COUNT(e.id) as klikov, MAX(l.ai_shown) as produktov')
            ->groupBy('l.query')
            ->havingRaw('COUNT(DISTINCT l.id) >= ? AND COUNT(e.id) = 0', [self::USELESS_MIN_SHOWN])
            ->orderByDesc('zobrazene')
            ->limit(25)
            ->get()
            ->map(fn ($r) => ['query' => $r->query, 'zobrazene' => (int) $r->zobrazene, 'produktov' => (int) $r->produktov])
            ->all();
    }
}
