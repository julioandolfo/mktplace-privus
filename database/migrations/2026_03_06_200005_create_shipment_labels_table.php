<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipment_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('melhor_envios_account_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('me_label_id')->nullable()->unique(); // ID da etiqueta no Melhor Envios
            $table->string('carrier')->nullable();    // transportadora (ex: correios, jadlog)
            $table->string('service')->nullable();    // serviço (ex: PAC, SEDEX)
            $table->string('tracking_code')->nullable();
            $table->decimal('cost', 10, 2)->nullable();           // custo cobrado pelo ME
            $table->decimal('customer_paid', 10, 2)->nullable();  // frete pago pelo cliente

            $table->string('label_url')->nullable();  // URL do PDF da etiqueta
            $table->string('status')->default('pending'); // pending|purchased|printed|cancelled

            $table->timestamp('quoted_at')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->jsonb('meta')->nullable(); // resposta completa da cotação/compra do ME

            $table->timestamps();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_labels');
    }
};
