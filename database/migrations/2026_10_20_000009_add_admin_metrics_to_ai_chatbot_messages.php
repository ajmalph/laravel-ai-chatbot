<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('ai-chatbot.tables.messages'), function (Blueprint $table): void {
            $table->unsignedInteger('latency_ms')->nullable()->after('output_tokens');
            $table->decimal('estimated_cost_usd', 14, 8)->nullable()->after('latency_ms');
            $table->string('error_code', 64)->nullable()->index()->after('estimated_cost_usd');
        });
    }

    public function down(): void
    {
        Schema::table(config('ai-chatbot.tables.messages'), function (Blueprint $table): void {
            $table->dropIndex(['error_code']);
            $table->dropColumn(['latency_ms', 'estimated_cost_usd', 'error_code']);
        });
    }
};
