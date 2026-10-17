<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Stránka AI vo vyhľadávaní: náklady vs. zisk z objednávok cez výsledky AI, lievik,
 * kontrolná skupina. Tabuľky e-shopu test vytvára v sqlite (analytika nemá migrácie).
 */
class AiVyhladavanieTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config()->set('analytics.usd_eur', 0.5);
        config()->set('analytics.excluded_customer_ids', [2]);

        Schema::create('titi_search_log', function (Blueprint $t) {
            $t->id();
            $t->string('query');
            $t->unsignedInteger('results_count');
            $t->string('channel')->default('results');
            $t->unsignedSmallInteger('ai_shown')->default(0);
            $t->decimal('ai_cost', 10, 6)->nullable();
            $t->string('visitor_id')->nullable();
            $t->boolean('ai_holdout')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('titi_search_event', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('search_log_id');
            $t->unsignedInteger('product_id');
            $t->string('source');
            $t->string('action');
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('titi_search_order', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('order_id');
            $t->unsignedInteger('product_id');
            $t->unsignedBigInteger('search_log_id');
            $t->string('source');
            $t->unsignedInteger('quantity');
            $t->decimal('total', 12, 4);
            $t->decimal('zisk', 12, 4);
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('titi_visitor_order', function (Blueprint $t) {
            $t->unsignedInteger('order_id')->primary();
            $t->string('visitor_id');
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('titi_order', function (Blueprint $t) {
            $t->unsignedInteger('order_id')->primary();
            $t->unsignedInteger('customer_id');
            $t->unsignedInteger('order_status_id');
        });
        Schema::create('titi_order_product', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('order_id');
            $t->decimal('zisk_bdph', 12, 4);
        });
        Schema::create('titi_ai_usage_log', function (Blueprint $t) {
            $t->id();
            $t->decimal('cost_usd', 10, 6);
            $t->timestamp('created_at')->useCurrent();
        });

        $hour = now()->subHour()->toDateTimeString();
        $now = now()->toDateTimeString();

        DB::table('titi_search_log')->insert([
            // prázdne, AI ukázalo, zákazník klikol, pridal do košíka a objednal
            ['id' => 1, 'query' => 'sanon', 'results_count' => 0, 'ai_shown' => 3, 'ai_cost' => 1.0, 'visitor_id' => 'v1', 'ai_holdout' => 0, 'created_at' => $hour],
            // prázdne, AI ukázalo, objednávka nezaplatená (kartou zlyhala)
            ['id' => 2, 'query' => 'sanon', 'results_count' => 0, 'ai_shown' => 3, 'ai_cost' => 0, 'visitor_id' => 'v2', 'ai_holdout' => 0, 'created_at' => $hour],
            // AI ukázalo 3×, nikto neklikol
            ['id' => 3, 'query' => 'xyz', 'results_count' => 0, 'ai_shown' => 2, 'ai_cost' => 0, 'visitor_id' => null, 'ai_holdout' => null, 'created_at' => $hour],
            ['id' => 4, 'query' => 'xyz', 'results_count' => 0, 'ai_shown' => 2, 'ai_cost' => 0, 'visitor_id' => null, 'ai_holdout' => null, 'created_at' => $hour],
            ['id' => 5, 'query' => 'xyz', 'results_count' => 0, 'ai_shown' => 2, 'ai_cost' => 0, 'visitor_id' => null, 'ai_holdout' => null, 'created_at' => $hour],
            // bežné hľadanie s výsledkami
            ['id' => 6, 'query' => 'pero', 'results_count' => 5, 'ai_shown' => 0, 'ai_cost' => 0, 'visitor_id' => 'v4', 'ai_holdout' => 0, 'created_at' => $hour],
            // kontrolná skupina: prázdne hľadanie bez AI, potom objednal iný tovar
            ['id' => 7, 'query' => 'lepidlo', 'results_count' => 0, 'ai_shown' => 0, 'ai_cost' => 0, 'visitor_id' => 'v3', 'ai_holdout' => 1, 'created_at' => $hour],
        ]);
        DB::table('titi_search_event')->insert([
            ['search_log_id' => 1, 'product_id' => 20, 'source' => 'ai', 'action' => 'click'],
            ['search_log_id' => 1, 'product_id' => 20, 'source' => 'ai', 'action' => 'cart'],
            ['search_log_id' => 2, 'product_id' => 20, 'source' => 'ai', 'action' => 'click'],
            ['search_log_id' => 6, 'product_id' => 30, 'source' => 'web', 'action' => 'click'],
        ]);
        DB::table('titi_order')->insert([
            ['order_id' => 100, 'customer_id' => 50, 'order_status_id' => 4],
            ['order_id' => 101, 'customer_id' => 51, 'order_status_id' => 1], // nezaplatená
            ['order_id' => 102, 'customer_id' => 52, 'order_status_id' => 4],
            ['order_id' => 103, 'customer_id' => 53, 'order_status_id' => 4],
        ]);
        DB::table('titi_order_product')->insert([
            ['order_id' => 100, 'zisk_bdph' => 3],
            ['order_id' => 103, 'zisk_bdph' => 2],
        ]);
        DB::table('titi_search_order')->insert([
            ['order_id' => 100, 'product_id' => 20, 'search_log_id' => 1, 'source' => 'ai', 'quantity' => 2, 'total' => 8, 'zisk' => 3],
            ['order_id' => 101, 'product_id' => 20, 'search_log_id' => 2, 'source' => 'ai', 'quantity' => 1, 'total' => 4, 'zisk' => 5],
            ['order_id' => 102, 'product_id' => 30, 'search_log_id' => 6, 'source' => 'web', 'quantity' => 1, 'total' => 2, 'zisk' => 1],
        ]);
        DB::table('titi_visitor_order')->insert([
            ['order_id' => 100, 'visitor_id' => 'v1', 'created_at' => $now],
            ['order_id' => 101, 'visitor_id' => 'v2', 'created_at' => $now],
            ['order_id' => 103, 'visitor_id' => 'v3', 'created_at' => $now],
        ]);
        DB::table('titi_ai_usage_log')->insert(['cost_usd' => 1.0, 'created_at' => $hour]);
    }

    public function test_requires_login(): void
    {
        $this->get('/vyhladavanie/ai')->assertRedirect(route('login'));
    }

    public function test_costs_against_profit_from_ai_results(): void
    {
        $this->withSession(['analytics_authed' => true])
            ->get('/vyhladavanie/ai')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Vyhladavanie/Ai')
                ->where('ready', true)
                ->where('naklady.vyhladavanie', 0.5)
                ->where('naklady.aktualizacia', 0.5)
                ->where('naklady.spolu', 1) // (1 $ + 1 $) × 0,5
                // lievik AI: 5 zobrazení, 2 s klikom, 1 košík, 1 zaplatená objednávka
                ->where('funnel.ai.zobrazene', 5)
                ->where('funnel.ai.klik', 2)
                ->where('funnel.ai.kosik', 1)
                ->where('funnel.ai.objednane', 1)
                ->where('funnel.ai.kusov', 2)
                ->where('funnel.ai.trzba', 8)
                ->where('funnel.ai.zisk', 3)
                ->where('funnel.web.zobrazene', 1)
                ->where('funnel.web.objednavok', 1)
                ->where('funnel.web.zisk', 1)
                ->where('zarabajuce.0.query', 'sanon')
                ->where('zarabajuce.0.zisk', 3)
                ->has('zarabajuce', 1)
                ->where('nezmysly.0.query', 'xyz')
                ->where('nezmysly.0.zobrazene', 3)
                ->has('nezmysly', 1)
                ->where('mesiace.0.mesiac', now()->format('Y-m'))
                ->where('mesiace.0.zisk', 3)
                ->where('mesiace.0.bilancia', 2)
                ->has('mesiace', 6)
            );
    }

    public function test_holdout_compares_visitors_with_and_without_ai(): void
    {
        $this->withSession(['analytics_authed' => true])
            ->get('/vyhladavanie/ai')
            ->assertInertia(fn (AssertableInertia $page) => $page
                // s AI: v1 objednal (zisk 3), v2 len nezaplatenú → 1 z 2
                ->where('holdout.ai.navstevnikov', 2)
                ->where('holdout.ai.objednali', 1)
                ->where('holdout.ai.konverzia', 50)
                ->where('holdout.ai.zisk_na_navstevnika', 1.5)
                ->where('holdout.bez_ai.navstevnikov', 1)
                ->where('holdout.bez_ai.objednali', 1)
                ->where('holdout.bez_ai.zisk_na_navstevnika', 2)
            );
    }

    public function test_page_waits_for_eshop_tables(): void
    {
        Schema::drop('titi_search_order');

        $this->withSession(['analytics_authed' => true])
            ->get('/vyhladavanie/ai')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('ready', false));
    }
}
