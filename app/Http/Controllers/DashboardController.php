<?php

namespace App\Http\Controllers;

use App\Models\Eshop\EshopCustomer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Aktuálne obdobie — default: posledných 30 dní
        if ($request->filled('date_from') || $request->filled('date_to')) {
            $dateFrom = $request->filled('date_from')
                ? $request->date_from . ' 00:00:00'
                : '2000-01-01 00:00:00';
            $dateTo = $request->filled('date_to')
                ? $request->date_to . ' 23:59:59'
                : now()->endOfDay()->toDateTimeString();
        } else {
            $dateFrom = now()->subDays(29)->startOfDay()->toDateTimeString();
            $dateTo   = now()->endOfDay()->toDateTimeString();
        }

        // Predchádzajúce obdobie (rovnaká dĺžka, posunutá dozadu)
        $dtFrom     = new \DateTime($dateFrom);
        $dtTo       = new \DateTime($dateTo);
        $durationDays = (int) $dtFrom->diff($dtTo)->days;

        $prevTo   = (clone $dtFrom)->modify('-1 second');
        $prevFrom = (clone $prevTo)->modify("-{$durationDays} days")->setTime(0, 0, 0);

        $prevDateFrom = $prevFrom->format('Y-m-d H:i:s');
        $prevDateTo   = $prevTo->format('Y-m-d H:i:s');

        // Globálne štatistiky
        $totalZakaznikov   = EshopCustomer::count();
        $aktivovanych      = EshopCustomer::where('active', 1)->count();
        $aktualneProduktov = DB::table('titi_product')
            ->where('status', 1)->where('titi_eshop', 1)
            ->where('mopcena', '>', 0)->where('quantity', '>', 1)
            ->count();

        // Aktuálne obdobie
        $objednavkyObdobi = DB::table('titi_order')
            ->whereBetween('date_added', [$dateFrom, $dateTo])->count();
        $trzbaObdobi = (float) DB::table('titi_order')
            ->whereBetween('date_added', [$dateFrom, $dateTo])->sum('total_sdph');
        $noveRegistracie = DB::table('titi_customer')
            ->whereBetween('date_added', [$dateFrom, $dateTo])->count();
        $priemObjednavky = $objednavkyObdobi > 0
            ? round($trzbaObdobi / $objednavkyObdobi, 2) : 0;

        // Predchádzajúce obdobie
        $prevObjednavky = DB::table('titi_order')
            ->whereBetween('date_added', [$prevDateFrom, $prevDateTo])->count();
        $prevTrzba = (float) DB::table('titi_order')
            ->whereBetween('date_added', [$prevDateFrom, $prevDateTo])->sum('total_sdph');
        $prevRegistracie = DB::table('titi_customer')
            ->whereBetween('date_added', [$prevDateFrom, $prevDateTo])->count();
        $prevPriem = $prevObjednavky > 0
            ? round($prevTrzba / $prevObjednavky, 2) : 0;

        // Trendy (% zmena)
        $trends = [
            'objednavky' => $this->trend($objednavkyObdobi, $prevObjednavky),
            'trzba'      => $this->trend($trzbaObdobi, $prevTrzba),
            'registracie'=> $this->trend($noveRegistracie, $prevRegistracie),
            'priem'      => $this->trend($priemObjednavky, $prevPriem),
        ];

        // Trend grafy
        $objednavkyTrend = DB::table('titi_order')
            ->select(DB::raw('DATE(date_added) as den, COUNT(*) as pocet, SUM(total_sdph) as trzba'))
            ->whereBetween('date_added', [$dateFrom, $dateTo])
            ->groupBy('den')->orderBy('den')->get();

        $registracieTrend = DB::table('titi_customer')
            ->select(DB::raw('DATE(date_added) as den, COUNT(*) as pocet'))
            ->whereBetween('date_added', [$dateFrom, $dateTo])
            ->groupBy('den')->orderBy('den')->get();

        // Top 10 produktov
        $topProdukty = DB::table('titi_order_product as op')
            ->join('titi_order as o', 'op.order_id', '=', 'o.order_id')
            ->leftJoin('titi_product_description as pd', 'op.product_id', '=', 'pd.product_id')
            ->whereBetween('o.date_added', [$dateFrom, $dateTo])
            ->groupBy('op.product_id', 'pd.name')
            ->select(
                'op.product_id',
                DB::raw('COALESCE(pd.name, "(bez názvu)") as nazov'),
                DB::raw('SUM(op.quantity) as kusov'),
                DB::raw('SUM(op.price_sdph) as trzba')
            )
            ->orderByDesc('kusov')->limit(10)->get();

        // Top 10 zákazníkov
        $topZakaznici = DB::table('titi_order as o')
            ->join('titi_customer as c', 'o.customer_id', '=', 'c.customer_id')
            ->whereBetween('o.date_added', [$dateFrom, $dateTo])
            ->groupBy('o.customer_id', 'c.firstname', 'c.lastname', 'c.email')
            ->select(
                'o.customer_id',
                DB::raw("CONCAT(c.firstname, ' ', c.lastname) as meno"),
                'c.email',
                DB::raw('COUNT(o.order_id) as pocet_obj'),
                DB::raw('SUM(o.total_sdph) as celkova_suma')
            )
            ->orderByDesc('celkova_suma')->limit(10)->get();

        return Inertia::render('Dashboard', [
            'stats' => [
                'celkemZakaznikov' => $totalZakaznikov,
                'aktivovanych'     => $aktivovanych,
                'aktivneProduktov' => $aktualneProduktov,
                'objednavkyObdobi' => $objednavkyObdobi,
                'trzbaObdobi'      => round($trzbaObdobi, 2),
                'noveRegistracie'  => $noveRegistracie,
                'priemObjednavky'  => $priemObjednavky,
            ],
            'trends'           => $trends,
            'objednavkyTrend'  => $objednavkyTrend,
            'registracieTrend' => $registracieTrend,
            'topProdukty'      => $topProdukty,
            'topZakaznici'     => $topZakaznici,
            'obdobie' => [
                'date_from' => substr($dateFrom, 0, 10),
                'date_to'   => substr($dateTo, 0, 10),
            ],
        ]);
    }

    private function trend(float|int $current, float|int $prev): ?float
    {
        if ($prev == 0) {
            return $current > 0 ? null : null; // nedostatok dát na porovnanie
        }
        return round((($current - $prev) / $prev) * 100, 1);
    }
}
