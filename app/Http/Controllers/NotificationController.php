<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function poll(Request $request)
    {
        $excluded        = config('analytics.excluded_customer_ids', []);
        $sinceOrderId    = (int) $request->input('since_order_id', 0);
        $sinceCustomerId = (int) $request->input('since_customer_id', 0);

        // Prvé volanie — vrátime len aktuálne max IDčka, nič nenotifikujeme
        if ($sinceOrderId === 0 && $sinceCustomerId === 0) {
            return response()->json([
                'orders'          => [],
                'registrations'   => [],
                'max_order_id'    => (int) (DB::table('titi_order')->whereNotIn('customer_id', $excluded)->max('order_id') ?? 0),
                'max_customer_id' => (int) (DB::table('titi_customer')->whereNotIn('customer_id', $excluded)->max('customer_id') ?? 0),
            ]);
        }

        // Nové objednávky
        $newOrders = DB::table('titi_order as o')
            ->leftJoin('titi_customer as c', 'o.customer_id', '=', 'c.customer_id')
            ->where('o.order_id', '>', $sinceOrderId)
            ->whereNotIn('o.customer_id', $excluded)
            ->select(
                'o.order_id',
                'o.total_sdph',
                DB::raw("TRIM(CONCAT(COALESCE(c.firstname,''), ' ', COALESCE(c.lastname,''))) as meno")
            )
            ->orderBy('o.order_id')
            ->limit(10)
            ->get();

        // Nové registrácie
        $newRegistrations = DB::table('titi_customer')
            ->where('customer_id', '>', $sinceCustomerId)
            ->whereNotIn('customer_id', $excluded)
            ->select('customer_id', 'firstname', 'lastname', 'email')
            ->orderBy('customer_id')
            ->limit(10)
            ->get();

        return response()->json([
            'orders'          => $newOrders,
            'registrations'   => $newRegistrations,
            'max_order_id'    => $newOrders->isNotEmpty()
                ? (int) $newOrders->max('order_id')
                : $sinceOrderId,
            'max_customer_id' => $newRegistrations->isNotEmpty()
                ? (int) $newRegistrations->max('customer_id')
                : $sinceCustomerId,
        ]);
    }
}
