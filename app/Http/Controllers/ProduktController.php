<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProduktController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('titi_product as p')
            ->leftJoin('titi_product_description as pd', 'p.product_id', '=', 'pd.product_id')
            ->select(
                'p.product_id',
                'p.status',
                'p.titi_eshop',
                'p.mopcena',
                'p.quantity',
                'p.performance_score',
                'p.visited',
                'p.sales',
                DB::raw('COALESCE(pd.name, "") as nazov'),
                DB::raw('CASE WHEN pd.description IS NOT NULL AND pd.description != "" THEN 1 ELSE 0 END as ma_popis')
            );

        // Filter: v_eshope — zapína všetky 4 podmienky naraz (default: '1')
        $vEshope = $request->input('v_eshope', '1');
        if ($vEshope === '1') {
            $query->where('p.status', 1)
                  ->where('p.titi_eshop', 1)
                  ->where('p.mopcena', '>', 0)
                  ->where('p.quantity', '>', 1);
        } else {
            if ($request->filled('status') && $request->status !== 'all') {
                $query->where('p.status', (int) $request->status);
            }
            if ($request->filled('titi_eshop') && $request->titi_eshop !== 'all') {
                $query->where('p.titi_eshop', (int) $request->titi_eshop);
            }
            if ($request->filled('ma_cenu') && $request->ma_cenu !== 'all') {
                if ($request->ma_cenu === '1') {
                    $query->where('p.mopcena', '>', 0);
                } else {
                    $query->where('p.mopcena', '<=', 0);
                }
            }
            if ($request->filled('na_sklade') && $request->na_sklade !== 'all') {
                if ($request->na_sklade === '1') {
                    $query->where('p.quantity', '>', 1);
                } else {
                    $query->where('p.quantity', '<=', 1);
                }
            }
        }

        // Filter: má popis
        if ($request->filled('ma_popis') && $request->ma_popis !== 'all') {
            if ($request->ma_popis === '1') {
                $query->whereNotNull('pd.description')->where('pd.description', '!=', '');
            } else {
                $query->where(function ($q) {
                    $q->whereNull('pd.description')->orWhere('pd.description', '=', '');
                });
            }
        }

        // Filter: bez predajov (sales = 0 alebo NULL)
        if ($request->filled('bez_predajov') && $request->bez_predajov === '1') {
            $query->where(function ($q) {
                $q->whereNull('p.sales')->orWhere('p.sales', '=', 0);
            });
        }

        // Vyhľadávanie
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('pd.name', 'like', "%{$search}%")
                  ->orWhere('p.product_id', 'like', "%{$search}%");
            });
        }

        // Zoradenie
        $allowedSorts = ['nazov', 'performance_score', 'sales', 'visited', 'mopcena', 'product_id'];
        $sort      = in_array($request->sort, $allowedSorts) ? $request->sort : 'product_id';
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';

        $sortColumn = match ($sort) {
            'nazov'             => 'pd.name',
            'performance_score' => 'p.performance_score',
            'sales'             => 'p.sales',
            'visited'           => 'p.visited',
            'mopcena'           => 'p.mopcena',
            default             => 'p.product_id',
        };

        $produkty = $query->orderBy($sortColumn, $direction)->paginate(25)->withQueryString();

        // Globálne štatistiky produktov v e-shope (vždy bez filtra)
        $eshopBase = DB::table('titi_product as p')
            ->where('p.status', 1)
            ->where('p.titi_eshop', 1)
            ->where('p.mopcena', '>', 0)
            ->where('p.quantity', '>', 1);

        $produktyStats = [
            'celkomVEshope' => (clone $eshopBase)->count(),
            'bezPopisu'     => (clone $eshopBase)
                ->leftJoin('titi_product_description as pd', 'p.product_id', '=', 'pd.product_id')
                ->where(function ($q) {
                    $q->whereNull('pd.description')->orWhere('pd.description', '=', '');
                })
                ->count(),
            'bezPredajov'   => (clone $eshopBase)
                ->where(function ($q) {
                    $q->whereNull('p.sales')->orWhere('p.sales', '=', 0);
                })
                ->count(),
        ];

        return Inertia::render('Produkty/Index', [
            'produkty'      => $produkty,
            'produktyStats' => $produktyStats,
            'filters'       => array_merge(
                ['v_eshope' => $vEshope],
                $request->only(['status', 'titi_eshop', 'ma_cenu', 'na_sklade', 'ma_popis', 'bez_predajov', 'search', 'sort', 'direction'])
            ),
        ]);
    }

    public function show(int $id)
    {
        $produkt = DB::table('titi_product as p')
            ->leftJoin('titi_product_description as pd', 'p.product_id', '=', 'pd.product_id')
            ->where('p.product_id', $id)
            ->select(
                'p.*',
                DB::raw('COALESCE(pd.name, "") as nazov'),
                DB::raw('COALESCE(pd.description, "") as popis'),
                DB::raw('COALESCE(pd.meta_description, "") as meta_popis'),
                DB::raw('CASE WHEN pd.description IS NOT NULL AND pd.description != "" THEN 1 ELSE 0 END as ma_popis')
            )
            ->first();

        if (! $produkt) {
            abort(404);
        }

        $pocetObjednavok = DB::table('titi_order_product')
            ->where('product_id', $id)
            ->distinct('order_id')
            ->count('order_id');

        $poslednehObjednavky = DB::table('titi_order_product as op')
            ->join('titi_order as o', 'op.order_id', '=', 'o.order_id')
            ->leftJoin('titi_customer as c', 'o.customer_id', '=', 'c.customer_id')
            ->where('op.product_id', $id)
            ->select(
                'op.order_id',
                'o.date_added',
                'op.quantity',
                'op.price_sdph',
                DB::raw("CONCAT(c.firstname, ' ', c.lastname) as meno_zakaznika")
            )
            ->orderByDesc('o.date_added')
            ->limit(10)
            ->get();

        return Inertia::render('Produkty/Detail', [
            'produkt'             => $produkt,
            'pocetObjednavok'     => $pocetObjednavok,
            'poslednehObjednavky' => $poslednehObjednavky,
        ]);
    }
}
