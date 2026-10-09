<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Stránka Vyhľadávanie nad titi_search_log / titi_search_event (tabuľky e-shopu –
 * analytika nemá vlastné migrácie, preto ich test vytvorí v sqlite).
 */
class VyhladavanieTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        Schema::create('titi_search_log', function (Blueprint $t) {
            $t->id();
            $t->string('query');
            $t->unsignedInteger('results_count');
            $t->string('channel')->default('results');
            $t->timestamp('created_at')->useCurrent();
            $t->text('web_product_ids')->nullable();
            $t->unsignedSmallInteger('ai_count')->nullable();
            $t->text('ai_product_ids')->nullable();
            $t->unsignedSmallInteger('ai_shown')->default(0);
            $t->unsignedInteger('ai_ms')->nullable();
            $t->boolean('ai_cached')->nullable();
            $t->boolean('ai_capped')->nullable();
            $t->boolean('ai_error')->nullable();
            $t->decimal('ai_cost', 10, 6)->nullable();
        });
        Schema::create('titi_search_event', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('search_log_id');
            $t->unsignedInteger('product_id');
            $t->string('source');
            $t->string('action');
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('titi_product_description', function (Blueprint $t) {
            $t->unsignedInteger('product_id');
            $t->string('name');
        });

        $now = now()->subMinutes(5)->toDateTimeString();
        DB::table('titi_search_log')->insert([
            // web radí zle: AI našlo papier A4, web krabice (zhoda 1/2)
            ['id' => 1, 'query' => 'papier a4 biely', 'results_count' => 84, 'created_at' => $now,
                'web_product_ids' => '10,11,12', 'ai_count' => 2, 'ai_product_ids' => '12,13', 'ai_ms' => 2300, 'ai_cached' => 0, 'ai_cost' => 0.00012],
            // prázdne, AI zachráni
            ['id' => 2, 'query' => 'sanon', 'results_count' => 0, 'created_at' => $now,
                'web_product_ids' => '', 'ai_count' => 3, 'ai_product_ids' => '20,21,22', 'ai_ms' => 1200, 'ai_cached' => 1, 'ai_cost' => 0],
            // prázdne, AI nič
            ['id' => 3, 'query' => 'pentel', 'results_count' => 0, 'created_at' => $now,
                'web_product_ids' => '', 'ai_count' => 0, 'ai_product_ids' => '', 'ai_ms' => 900, 'ai_cached' => 0, 'ai_cost' => 0.0001],
        ]);
        // našepkávač: nič nenašiel a zákazník odišiel (AI v tieni tiež nič) + výber produktu
        DB::table('titi_search_log')->insert([
            ['id' => 4, 'query' => 'xyzq', 'results_count' => 0, 'channel' => 'suggest', 'created_at' => $now,
                'ai_count' => 0, 'ai_product_ids' => '', 'ai_ms' => 700, 'ai_cached' => 0, 'ai_cost' => 0.0001],
            ['id' => 5, 'query' => 'pero', 'results_count' => 4, 'channel' => 'suggest', 'created_at' => $now,
                'ai_count' => null, 'ai_product_ids' => null, 'ai_ms' => null, 'ai_cached' => null, 'ai_cost' => null],
        ]);
        DB::table('titi_search_event')->insert([
            ['search_log_id' => 5, 'product_id' => 13, 'source' => 'suggest', 'action' => 'click'],
            ['search_log_id' => 2, 'product_id' => 20, 'source' => 'ai', 'action' => 'click'],
            ['search_log_id' => 2, 'product_id' => 20, 'source' => 'ai', 'action' => 'cart'],
            ['search_log_id' => 1, 'product_id' => 10, 'source' => 'web', 'action' => 'click'],
        ]);
        DB::table('titi_product_description')->insert([
            ['product_id' => 10, 'name' => 'Krabica na tortu'],
            ['product_id' => 12, 'name' => 'Papier kop. biely A4 PREMIER'],
            ['product_id' => 13, 'name' => 'Papier kop. biely A4 GO COPY'],
        ]);
    }

    public function test_requires_login(): void
    {
        $this->get('/vyhladavanie')->assertRedirect(route('login'));
    }

    public function test_page_shows_stats_rescue_and_differences(): void
    {
        $this->withSession(['analytics_authed' => true])
            ->get('/vyhladavanie')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Vyhladavanie/Index')
                ->where('stats.total', 5)
                ->where('stats.suggest', 2)
                ->where('stats.zero', 3)
                ->where('stats.rescuable', 1)
                ->where('stats.nobody', 2)
                ->where('stats.aiChecked', 4)
                ->where('events.suggest.click', 1)
                ->where('events.suggest.category', 0)
                ->where('events.ai.click', 1)
                ->where('events.ai.cart', 1)
                ->where('events.web.click', 1)
                ->where('differences.0.query', 'papier a4 biely')
                ->where('differences.0.zhoda', 50)
                ->has('topZero', 3)
                ->has('live', 5)
            );
    }

    public function test_live_returns_only_new_rows_and_today_totals(): void
    {
        $this->withSession(['analytics_authed' => true])
            ->getJson('/vyhladavanie/live?since_id=3')
            ->assertOk()
            ->assertJsonCount(2, 'rows')
            ->assertJsonPath('rows.0.query', 'pero')
            ->assertJsonPath('rows.0.channel', 'suggest')
            ->assertJsonPath('today.hladani', 5)
            ->assertJsonPath('today.zachranitelnych', 1);
    }

    public function test_comparison_marks_products_found_by_both(): void
    {
        $this->withSession(['analytics_authed' => true])
            ->getJson('/vyhladavanie/1/porovnanie')
            ->assertOk()
            ->assertJsonPath('web.0.name', 'Krabica na tortu')
            ->assertJsonPath('web.0.v_druhom', false)
            ->assertJsonPath('web.0.kliky', 1)
            ->assertJsonPath('ai.0.name', 'Papier kop. biely A4 PREMIER')
            ->assertJsonPath('ai.0.v_druhom', true);
    }

    public function test_comparison_404_for_unknown_search(): void
    {
        $this->withSession(['analytics_authed' => true])
            ->getJson('/vyhladavanie/999/porovnanie')
            ->assertNotFound();
    }
}
