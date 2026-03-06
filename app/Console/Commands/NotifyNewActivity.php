<?php

namespace App\Console\Commands;

use App\Mail\NewActivityMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class NotifyNewActivity extends Command
{
    protected $signature   = 'notify:new-activity';
    protected $description = 'Skontroluje nové objednávky a registrácie a pošle emailové upozornenie';

    public function handle(): void
    {
        $notifyEmail = config('analytics.notify_email');
        if (! $notifyEmail) {
            $this->warn('ANALYTICS_NOTIFY_EMAIL nie je nastavený v .env — emaily sa neposielajú.');
            return;
        }

        $lastOrderId    = (int) Cache::get('notify_last_order_id', -1);
        $lastCustomerId = (int) Cache::get('notify_last_customer_id', -1);

        // Prvý beh — nastav aktuálne max IDčka a neposielajú sa žiadne emaily
        if ($lastOrderId === -1 || $lastCustomerId === -1) {
            Cache::forever('notify_last_order_id',    (int) (DB::table('titi_order')->max('order_id') ?? 0));
            Cache::forever('notify_last_customer_id', (int) (DB::table('titi_customer')->max('customer_id') ?? 0));
            $this->info('Prvý beh — inicializované max IDčka. Ďalší beh bude sledovať nové záznamy.');
            return;
        }

        // Nové objednávky
        $newOrders = DB::table('titi_order as o')
            ->leftJoin('titi_customer as c', 'o.customer_id', '=', 'c.customer_id')
            ->where('o.order_id', '>', $lastOrderId)
            ->select(
                'o.order_id',
                'o.total_sdph',
                'o.date_added',
                DB::raw("TRIM(CONCAT(COALESCE(c.firstname,''), ' ', COALESCE(c.lastname,''))) as meno"),
                'c.email as zakaznik_email'
            )
            ->orderBy('o.order_id')
            ->get();

        // Nové registrácie
        $newRegistrations = DB::table('titi_customer')
            ->where('customer_id', '>', $lastCustomerId)
            ->select('customer_id', 'firstname', 'lastname', 'email', 'date_added')
            ->orderBy('customer_id')
            ->get();

        if ($newOrders->isEmpty() && $newRegistrations->isEmpty()) {
            return; // Nič nové
        }

        // Pošli email
        Mail::to($notifyEmail)->send(new NewActivityMail($newOrders, $newRegistrations));

        // Aktualizuj cache
        if ($newOrders->isNotEmpty()) {
            Cache::forever('notify_last_order_id', (int) $newOrders->max('order_id'));
        }
        if ($newRegistrations->isNotEmpty()) {
            Cache::forever('notify_last_customer_id', (int) $newRegistrations->max('customer_id'));
        }

        $this->info("Odoslaný email: {$newOrders->count()} obj., {$newRegistrations->count()} reg.");
    }
}
