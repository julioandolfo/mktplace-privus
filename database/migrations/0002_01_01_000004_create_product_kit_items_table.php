<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_kit_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kit_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('component_product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('component_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->integer('quantity')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['kit_product_id', 'component_product_id', 'component_variant_id'], 'kit_component_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_kit_items');
    }
};
