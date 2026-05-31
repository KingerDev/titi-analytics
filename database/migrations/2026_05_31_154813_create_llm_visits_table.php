<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('llm_visits', function (Blueprint $table) {
            $table->id();
            $table->string('source', 32)->index();          // chatgpt, claude, perplexity, gemini, gptbot, claudebot, ccbot, ...
            $table->enum('kind', ['bot', 'human'])->index(); // bot = crawler (user-agent), human = klik z LLM UI (referer)
            $table->string('path', 500);                     // request URI
            $table->string('referer', 500)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('ip_hash', 64)->nullable();       // sha256 hash IP — GDPR friendly
            $table->string('country', 2)->nullable();        // ISO 3166-1 alpha-2 (voliteľne, ak by sme dopĺňali)
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['created_at', 'source']);
            $table->index(['created_at', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('llm_visits');
    }
};
