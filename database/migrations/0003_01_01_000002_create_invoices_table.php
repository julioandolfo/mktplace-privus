<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('number')->nullable();
            $table->string('series')->nullable()->default('1');
            $table->string('access_key', 44)->nullable()->unique();
            $table->string('protocol')->nullable();
            $table->string('status')->default('pending');
            $table->string('type')->default('nfe'); // nfe, nfce

            // Customer
            $table->string('customer_name');
            $table->string('customer_document')->nullable();
            $table->jsonb('customer_address')->nullable();

            // Values
            $table->decimal('total_products', 12, 2)->default(0);
            $table->decimal('total_shipping', 12, 2)->default(0);
            $table->decimal('total_discount', 12, 2)->default(0);
            $table->decimal('total_tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            // Tax data
            $table->string('nature_operation')->default('Venda de mercadoria');
            $table->jsonb('tax_data')->nullable();

            // PDF/XML
            $table->text('pdf_url')->nullable();
            $table->text('xml_url')->nullable();
            $table->text('xml_content')->nullable();

            // Rejection/Cancellation
            $table->text('rejection_reason')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            // External integration
            $table->string('external_id')->nullable();
            $table->jsonb('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('order_id');
            $table->index('company_id');
            $table->index('number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
