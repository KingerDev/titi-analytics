<?php

namespace App\Http\Controllers;

use App\Models\Eshop\EshopCustomer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RegistracieController extends Controller
{
    public function index(Request $request)
    {
        $query = EshopCustomer::query()
            ->select('customer_id', 'firstname', 'lastname', 'email', 'active', 'google_id', 'apple_id', 'date_added');

        if ($request->filled('activated') && $request->activated !== 'all') {
            $query->where('active', (int) $request->activated);
        }

        if ($request->filled('method') && $request->method !== 'all') {
            match ($request->method) {
                'google' => $query->whereNotNull('google_id'),
                'apple'  => $query->whereNotNull('apple_id'),
                'email'  => $query->whereNull('google_id')->whereNull('apple_id'),
                default  => null,
            };
        }

        if ($request->filled('date_from')) {
            $query->where('date_added', '>=', $request->date_from . ' 00:00:00');
        }
        if ($request->filled('date_to')) {
            $query->where('date_added', '<=', $request->date_to . ' 23:59:59');
        }

        // Zoradenie
        $allowedSorts = ['date_added', 'firstname', 'active'];
        $sort      = in_array($request->sort, $allowedSorts) ? $request->sort : 'date_added';
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';

        $zakaznici = $query->orderBy($sort, $direction)->paginate(20)->withQueryString();

        $stats = [
            'celkom'          => EshopCustomer::count(),
            'aktivovanych'    => EshopCustomer::where('active', 1)->count(),
            'neaktivovanych'  => EshopCustomer::where('active', 0)->count(),
            'email'           => EshopCustomer::whereNull('google_id')->whereNull('apple_id')->count(),
            'google'          => EshopCustomer::whereNotNull('google_id')->count(),
            'apple'           => EshopCustomer::whereNotNull('apple_id')->count(),
        ];

        return Inertia::render('Registracie/Index', [
            'zakaznici' => $zakaznici,
            'stats'     => $stats,
            'filters'   => $request->only(['activated', 'method', 'date_from', 'date_to', 'sort', 'direction']),
        ]);
    }
}
