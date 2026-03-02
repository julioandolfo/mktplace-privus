<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('marketplace_type');
            $table->string('account_name');
            $table->string('shop_id')->nullable();
            $table->text('credentials')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->string('status')->default('inactive');
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->jsonb('settings')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'marketplace_type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_accounts');
    }
};
