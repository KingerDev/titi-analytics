<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class LlmVisitsController extends Controller
{
    use ResolvesPeriod;

    /**
     * Human-readable labels pre source slug → zobrazenie v UI.
     *
     * @var array<string, string>
     */
    private const SOURCE_LABELS = [
        // Boty
        'gptbot' => 'GPTBot (OpenAI)',
        'oai-searchbot' => 'OAI-SearchBot',
        'chatgpt-user' => 'ChatGPT-User',
        'claudebot' => 'ClaudeBot (Anthropic)',
        'perplexitybot' => 'PerplexityBot',
        'google-extended' => 'Google-Extended (Gemini)',
        'ccbot' => 'CCBot (Common Crawl)',
        'applebot-extended' => 'Apple Intelligence',
        'meta-externalagent' => 'Meta (LLaMA)',
        'bytespider' => 'Bytespider (ByteDance)',
        'mistralai' => 'MistralAI',
        'youbot' => 'You.com',
        'duckassistbot' => 'DuckAssistBot',
        'cohere-ai' => 'Cohere',
        'diffbot' => 'Diffbot',
        'timpibot' => 'Timpi',
        // Human referers
        'chatgpt' => 'ChatGPT',
        'claude' => 'Claude',
        'perplexity' => 'Perplexity',
        'gemini' => 'Gemini',
        'copilot' => 'Microsoft Copilot',
        'phind' => 'Phind',
        'mistral' => 'Mistral Chat',
        'kagi' => 'Kagi',
        'poe' => 'Poe',
        'you' => 'You.com',
        'duckassist' => 'DuckDuckGo AI',
    ];

    public function index(Request $request)
    {
        [$dateFrom, $dateTo, $prevDateFrom, $prevDateTo] = $this->resolvePeriod($request);

        $base = DB::table('llm_visits')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        $totalVisits = (clone $base)->count();
        $botVisits = (clone $base)->where('kind', 'bot')->count();
        $humanVisits = (clone $base)->where('kind', 'human')->count();
        $uniqueSources = (clone $base)->distinct('source')->count('source');

        // Predchádzajúce obdobie (rovnaká dĺžka)
        $prevBase = DB::table('llm_visits')
            ->whereBetween('created_at', [$prevDateFrom, $prevDateTo]);
        $prevTotal = (clone $prevBase)->count();
        $prevBot = (clone $prevBase)->where('kind', 'bot')->count();
        $prevHuman = (clone $prevBase)->where('kind', 'human')->count();

        $trends = [
            'total' => $this->trend($totalVisits, $prevTotal),
            'bot' => $this->trend($botVisits, $prevBot),
            'human' => $this->trend($humanVisits, $prevHuman),
        ];

        // Denný graf (bot + human breakdown)
        $dailyRows = (clone $base)
            ->selectRaw('DATE(created_at) as den, kind, COUNT(*) as pocet')
            ->groupBy('den', 'kind')
            ->orderBy('den')
            ->get();

        $daysMap = [];
        foreach ($dailyRows as $row) {
            $daysMap[$row->den]['den'] = $row->den;
            $daysMap[$row->den][$row->kind] = (int) $row->pocet;
        }
        $dailyTrend = array_values(array_map(fn ($d) => [
            'den' => $d['den'],
            'bot' => $d['bot'] ?? 0,
            'human' => $d['human'] ?? 0,
        ], $daysMap));

        // Rozdelenie podľa zdroja
        $bySource = (clone $base)
            ->selectRaw('source, kind, COUNT(*) as pocet')
            ->groupBy('source', 'kind')
            ->orderByDesc('pocet')
            ->get()
            ->map(fn ($row) => [
                'source' => $row->source,
                'label' => self::SOURCE_LABELS[$row->source] ?? $row->source,
                'kind' => $row->kind,
                'pocet' => (int) $row->pocet,
            ])
            ->values();

        // Top stránky navštívené z LLM
        $topPaths = (clone $base)
            ->selectRaw('path, COUNT(*) as pocet, SUM(CASE WHEN kind = "human" THEN 1 ELSE 0 END) as human_pocet, SUM(CASE WHEN kind = "bot" THEN 1 ELSE 0 END) as bot_pocet')
            ->groupBy('path')
            ->orderByDesc('pocet')
            ->limit(25)
            ->get()
            ->map(fn ($row) => [
                'path' => $row->path,
                'pocet' => (int) $row->pocet,
                'human_pocet' => (int) $row->human_pocet,
                'bot_pocet' => (int) $row->bot_pocet,
            ])
            ->values();

        return Inertia::render('LlmVisits/Index', [
            'stats' => [
                'totalVisits' => $totalVisits,
                'botVisits' => $botVisits,
                'humanVisits' => $humanVisits,
                'uniqueSources' => $uniqueSources,
            ],
            'trends' => $trends,
            'dailyTrend' => $dailyTrend,
            'bySource' => $bySource,
            'topPaths' => $topPaths,
            'obdobie' => [
                'date_from' => substr($dateFrom, 0, 10),
                'date_to' => substr($dateTo, 0, 10),
            ],
        ]);
    }
}
