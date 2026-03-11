<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Configuração de bonificação por empresa
        Schema::create('expedition_bonus_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('points_value_cents')->default(10);          // R$0,10 por ponto
            $table->unsignedSmallInteger('default_product_points')->default(1);  // pontos padrão por unidade
            $table->unsignedSmallInteger('deadline_buffer_days')->default(1);    // dias de folga antes do prazo
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('company_id');
        });

        // 2. Coluna de pontos customizados no produto
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedSmallInteger('expedition_points')->nullable()->after('meta');
        });

        // 3. Log de pontos ganhos por operador
        Schema::create('expedition_points_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained('expedition_operators')->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->string('event_type', 30);  // packing, shipping
            $table->unsignedInteger('points')->default(0);
            $table->date('reference_date');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'reference_date']);
            $table->index(['operator_id', 'reference_date']);
            $table->index(['order_id', 'event_type']);
        });

        // 4. Metas mensais (calculadas ou manuais)
        Schema::create('expedition_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->date('month');                                              // primeiro dia do mês
            $table->unsignedInteger('total_pending_orders')->default(0);
            $table->unsignedSmallInteger('working_days')->default(22);
            $table->unsignedInteger('daily_order_goal')->default(0);
            $table->unsignedInteger('total_points_earned')->default(0);         // preenchido no fechamento
            $table->unsignedInteger('total_value_cents')->default(0);           // preenchido no fechamento
            $table->boolean('is_locked')->default(false);                       // true = mês fechado
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'month']);
        });

        // 5. Snapshot de fechamento por operador
        Schema::create('expedition_goal_operators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->constrained('expedition_goals')->cascadeOnDelete();
            $table->foreignId('operator_id')->constrained('expedition_operators')->cascadeOnDelete();
            $table->unsignedInteger('total_points')->default(0);
            $table->unsignedInteger('total_orders')->default(0);
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('value_cents')->default(0);
            $table->timestamps();

            $table->unique(['goal_id', 'operator_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expedition_goal_operators');
        Schema::dropIfExists('expedition_goals');
        Schema::dropIfExists('expedition_points_log');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('expedition_points');
        });
        Schema::dropIfExists('expedition_bonus_configs');
    }
};
