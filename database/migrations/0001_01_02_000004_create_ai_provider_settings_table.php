<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_provider_settings', function (Blueprint $table) {
            $table->id();
            $table->string('provider');
            $table->text('api_key')->nullable();
            $table->string('base_url')->nullable();
            $table->string('default_model')->nullable();
            $table->jsonb('settings')->nullable();
            $table->decimal('monthly_budget_limit', 10, 2)->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_settings');
    }
};
