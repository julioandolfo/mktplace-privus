<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('melhor_envios_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('client_id');
            $table->text('client_secret');
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('environment')->default('sandbox'); // sandbox | production
            $table->string('from_name')->nullable();
            $table->string('from_document')->nullable();
            $table->string('from_cep')->nullable();
            $table->jsonb('from_address')->nullable();
            $table->jsonb('default_package')->nullable(); // {weight, width, height, length}
            $table->jsonb('settings')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('melhor_envios_accounts');
    }
};
