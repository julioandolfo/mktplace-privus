<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webmania_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->text('consumer_key')->nullable();
            $table->text('consumer_secret')->nullable();
            $table->text('access_token')->nullable();
            $table->text('access_token_secret')->nullable();
            $table->string('environment')->default('homologacao');
            $table->string('default_series')->default('1');
            $table->string('default_cfop')->nullable();
            $table->jsonb('default_tax_data')->nullable();
            $table->timestamp('certificate_expires_at')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webmania_accounts');
    }
};
