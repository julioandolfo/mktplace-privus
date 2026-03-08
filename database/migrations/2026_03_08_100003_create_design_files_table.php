<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('design_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();

            $table->string('file_type', 50)->default('production_file');
            // file_type: mockup, artwork, production_file, reference

            $table->string('file_name', 500);
            $table->string('file_path', 2000)->nullable();
            $table->text('file_url')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('disk', 50)->default('public');

            $table->boolean('is_production_file')->default(false);
            $table->boolean('is_ai_generated')->default(false);
            $table->text('ai_prompt')->nullable();

            $table->timestamps();

            $table->index(['design_assignment_id', 'file_type']);
            $table->index('is_production_file');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_files');
    }
};
