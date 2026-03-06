<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ObjednavkyController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('titi_order as o')
            ->leftJoin('titi_customer as c', 'o.customer_id', '=', 'c.customer_id')
            ->select(
                'o.order_id',
                'o.date_added',
                'o.total_sdph',
                'o.payment_method',
                'o.order_status_id',
                DB::raw("CONCAT(c.firstname, ' ', c.lastname) as meno_zakaznika"),
                DB::raw('(SELECT COUNT(*) FROM titi_order_product op WHERE op.order_id = o.order_id) as pocet_poloziek')
            );

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('c.firstname', 'like', "%{$search}%")
                  ->orWhere('c.lastname', 'like', "%{$search}%")
                  ->orWhere('c.email', 'like', "%{$search}%")
                  ->orWhere('o.order_id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_from')) {
            $query->where('o.date_added', '>=', $request->date_from . ' 00:00:00');
        }
        if ($request->filled('date_to')) {
            $query->where('o.date_added', '<=', $request->date_to . ' 23:59:59');
        }

        // Zoradenie
        $allowedSorts = ['order_id', 'date_added', 'total_sdph'];
        $sort      = in_array($request->sort, $allowedSorts) ? $request->sort : 'date_added';
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';

        $sortColumn = match ($sort) {
            'order_id'   => 'o.order_id',
            'total_sdph' => 'o.total_sdph',
            default      => 'o.date_added',
        };

        $objednavky = $query->orderBy($sortColumn, $direction)->paginate(20)->withQueryString();

        return Inertia::render('Objednavky/Index', [
            'objednavky' => $objednavky,
            'filters'    => $request->only(['search', 'date_from', 'date_to', 'sort', 'direction']),
        ]);
    }

    public function show(int $id)
    {
        $objednavka = DB::table('titi_order as o')
            ->leftJoin('titi_customer as c', 'o.customer_id', '=', 'c.customer_id')
            ->where('o.order_id', $id)
            ->select(
                'o.*',
                DB::raw("CONCAT(c.firstname, ' ', c.lastname) as meno_zakaznika"),
                'c.email as zakaznik_email',
                'c.customer_id'
            )
            ->first();

        if (! $objednavka) {
            abort(404);
        }

        $polozky = DB::table('titi_order_product as op')
            ->leftJoin('titi_product as p', 'op.product_id', '=', 'p.product_id')
            ->where('op.order_id', $id)
            ->select(
                'op.order_product_id',
                'op.product_id',
                'op.name',
                'op.quantity',
                'op.price_sdph',
                'op.jprice_sdph',
                'p.status',
                'p.performance_score'
            )
            ->get();

        return Inertia::render('Objednavky/Detail', [
            'objednavka' => $objednavka,
            'polozky'    => $polozky,
        ]);
    }
}
