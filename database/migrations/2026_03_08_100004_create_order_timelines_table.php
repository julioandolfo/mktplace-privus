<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_timelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('event_type', 100);
            // order_created, payment_confirmed, design_assigned, design_started,
            // design_completed, production_started, production_completed,
            // ready_to_ship, invoice_emitted, shipped, delivered, note_added, etc.

            $table->string('title', 300);
            $table->text('description')->nullable();
            $table->jsonb('data')->nullable();

            $table->timestamp('happened_at');
            $table->timestamps();

            $table->index(['order_id', 'happened_at']);
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_timelines');
    }
};
