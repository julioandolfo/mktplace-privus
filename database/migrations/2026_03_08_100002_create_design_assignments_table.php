<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('designer_id')->constrained('users')->cascadeOnDelete();

            $table->string('status', 50)->default('pending');
            // status: pending, in_progress, revision, completed

            $table->jsonb('canvas_state')->nullable();
            $table->text('mockup_url')->nullable();
            $table->text('notes')->nullable();
            $table->text('revision_notes')->nullable();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['company_id', 'status']);
            $table->index('designer_id');
            $table->unique('order_id'); // um assignment ativo por pedido
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_assignments');
    }
};
