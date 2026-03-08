<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('romaneio_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('romaneio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->restrictOnDelete();

            // volumes de caixa: definido no board de expedição antes do romaneio
            $table->unsignedSmallInteger('volumes')->default(1);
            $table->unsignedSmallInteger('volumes_scanned')->default(0);

            // detalhes dos order_items incluídos (para envios parciais)
            // ex: [{"order_item_id": 42, "quantity": 2}, {"order_item_id": 43, "quantity": 1}]
            $table->jsonb('items_detail')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['romaneio_id', 'order_id']);
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('romaneio_items');
    }
};
