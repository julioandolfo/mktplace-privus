<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->text('short_description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('simple'); // simple, variable, kit
            $table->string('status')->default('draft'); // draft, active, inactive, archived
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('cost_price', 12, 2)->nullable();
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->decimal('weight', 10, 3)->nullable(); // grams
            $table->decimal('width', 8, 2)->nullable();   // cm
            $table->decimal('height', 8, 2)->nullable();   // cm
            $table->decimal('length', 8, 2)->nullable();   // cm
            $table->string('ncm')->nullable();
            $table->string('cest')->nullable();
            $table->string('ean_gtin')->nullable();
            $table->string('origin')->nullable();
            $table->jsonb('tax_data')->nullable();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->text('ai_generated_description')->nullable();
            $table->integer('ai_score')->nullable();
            $table->jsonb('attributes')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('type');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
