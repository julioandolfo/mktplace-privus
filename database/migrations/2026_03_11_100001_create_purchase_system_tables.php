<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Fornecedores
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('contact_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('website')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        // 2. Solicitações de compra
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('pending'); // pending, purchased, partial, cancelled
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('total_cost_cents')->default(0);
            $table->timestamp('purchased_at')->nullable();
            $table->foreignId('purchased_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'status']);
            $table->index(['order_id']);
        });

        // 3. Itens da solicitação de compra
        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->string('description');       // nome livre ou nome do produto
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('unit_cost_cents')->default(0);
            $table->string('link')->nullable();  // link para compra
            $table->string('status', 30)->default('pending'); // pending, purchased, cancelled
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 4. Campo no produto: exige compra?
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('requires_purchase')->default(false)->after('requires_artwork');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('suppliers');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('requires_purchase');
        });
    }
};
