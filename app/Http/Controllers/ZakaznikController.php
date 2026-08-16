<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ZakaznikController extends Controller
{
    public function index(Request $request)
    {
        $excluded = config('analytics.excluded_customer_ids', []);

        $query = DB::table('titi_customer as c')
            ->select(
                'c.customer_id',
                'c.firstname',
                'c.lastname',
                'c.email',
                'c.active',
                'c.google_id',
                'c.apple_id',
                'c.date_added',
                DB::raw('COUNT(o.order_id) as pocet_objednavok'),
                DB::raw('COALESCE(SUM(o.total_sdph), 0) as celkova_suma')
            )
            ->leftJoin('titi_order as o', 'c.customer_id', '=', 'o.customer_id')
            ->whereNotIn('c.customer_id', $excluded)
            ->groupBy('c.customer_id', 'c.firstname', 'c.lastname', 'c.email', 'c.active', 'c.google_id', 'c.apple_id', 'c.date_added');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('c.firstname', 'like', "%{$search}%")
                  ->orWhere('c.lastname', 'like', "%{$search}%")
                  ->orWhere('c.email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('activated') && $request->activated !== 'all') {
            $query->where('c.active', (int) $request->activated);
        }

        // Metóda registrácie — rovnaká precedencia ako badge vo Vue:
        // google > apple > email (26 zákazníkov má vyplnené oboje ID).
        $method = $request->input('method');

        if ($method && $method !== 'all') {
            $hasGoogle = fn ($q) => $q->whereNotNull('c.google_id')->where('c.google_id', '!=', '');
            $hasApple  = fn ($q) => $q->whereNotNull('c.apple_id')->where('c.apple_id', '!=', '');

            match ($method) {
                'google' => $query->where($hasGoogle),
                'apple'  => $query->where($hasApple)->where(function ($q) {
                    $q->whereNull('c.google_id')->orWhere('c.google_id', '=', '');
                }),
                'email'  => $query->where(function ($q) {
                    $q->whereNull('c.google_id')->orWhere('c.google_id', '=', '');
                })->where(function ($q) {
                    $q->whereNull('c.apple_id')->orWhere('c.apple_id', '=', '');
                }),
                default  => null,
            };
        }

        // Zoradenie
        $allowedSorts = ['date_added', 'celkova_suma', 'pocet_objednavok'];
        $sort      = in_array($request->sort, $allowedSorts) ? $request->sort : 'date_added';
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';

        $sortColumn = match ($sort) {
            'celkova_suma'     => DB::raw('celkova_suma'),
            'pocet_objednavok' => DB::raw('pocet_objednavok'),
            default            => 'c.date_added',
        };

        $zakaznici = $query->orderBy($sortColumn, $direction)->paginate(20)->withQueryString();

        return Inertia::render('Zakaznici/Index', [
            'zakaznici' => $zakaznici,
            'filters'   => $request->only(['search', 'activated', 'method', 'sort', 'direction']),
        ]);
    }

    public function show(int $id)
    {
        $excluded = config('analytics.excluded_customer_ids', []);

        if (in_array($id, $excluded)) {
            abort(404);
        }

        $zakaznik = DB::table('titi_customer')
            ->where('customer_id', $id)
            ->select('customer_id', 'firstname', 'lastname', 'email', 'active', 'google_id', 'apple_id', 'date_added', 'points', 'mobil')
            ->first();

        if (! $zakaznik) {
            abort(404);
        }

        $objednavky = DB::table('titi_order as o')
            ->where('o.customer_id', $id)
            ->select(
                'o.order_id',
                'o.date_added',
                'o.total_sdph',
                'o.payment_method',
                'o.order_status_id',
                DB::raw('(SELECT COUNT(*) FROM titi_order_product op WHERE op.order_id = o.order_id) as pocet_poloziek')
            )
            ->orderByDesc('o.date_added')
            ->get();

        $stats = [
            'pocet_objednavok' => $objednavky->count(),
            'celkova_suma'     => round($objednavky->sum('total_sdph'), 2),
        ];

        return Inertia::render('Zakaznici/Detail', [
            'zakaznik'   => $zakaznik,
            'objednavky' => $objednavky,
            'stats'      => $stats,
        ]);
    }

    /**
     * Prehodí meno a priezvisko zákazníka.
     *
     * Rieši chybu z Apple auth registrácie, kde sa firstname/lastname
     * zapisovali opačne. Zámena je idempotentná — druhý klik vráti pôvodný stav.
     * Mení iba titi_customer, adresy (titi_address) zostávajú nedotknuté.
     */
    public function swapName(int $id)
    {
        $excluded = config('analytics.excluded_customer_ids', []);

        if (in_array($id, $excluded)) {
            abort(404);
        }

        $zakaznik = DB::table('titi_customer')
            ->where('customer_id', $id)
            ->select('customer_id', 'firstname', 'lastname')
            ->first();

        if (! $zakaznik) {
            abort(404);
        }

        DB::table('titi_customer')
            ->where('customer_id', $id)
            ->update([
                'firstname' => $zakaznik->lastname,
                'lastname'  => $zakaznik->firstname,
            ]);

        return back(303);
    }
}
