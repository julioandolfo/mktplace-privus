<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('external_id')->unique()->comment('message_id do ML');
            $table->enum('direction', ['sent', 'received'])->default('received');
            $table->string('sender_user_id')->nullable();
            $table->text('text')->nullable();
            $table->string('status')->default('available')
                ->comment('available, moderated, rejected, pending_translation');
            $table->string('moderation_status')->default('non_moderated')
                ->comment('clean, rejected, pending, non_moderated');
            $table->boolean('is_read')->default(false);
            $table->jsonb('message_date')->nullable()
                ->comment('received, available, notified, created, read timestamps');
            $table->jsonb('attachments')->nullable();
            $table->timestamps();

            $table->index('order_id');
            $table->index(['order_id', 'direction']);
            $table->index('is_read');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_messages');
    }
};
