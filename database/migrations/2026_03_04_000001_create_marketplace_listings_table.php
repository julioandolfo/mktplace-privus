<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketplace_account_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('product_quantity')->default(1);
            $table->string('title');
            $table->decimal('price', 12, 2)->default(0);
            $table->unsignedInteger('available_quantity')->nullable();
            $table->string('status')->default('active'); // active, paused, closed, deleted
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->unique(['marketplace_account_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_listings');
    }
};
