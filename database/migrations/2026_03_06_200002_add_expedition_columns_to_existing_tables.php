<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // orders — pipeline status interno de expedição
        Schema::table('orders', function (Blueprint $table) {
            $table->string('pipeline_status')->default('ready_to_ship')->after('status');
        });

        // order_items — envios parciais + produção por item
        Schema::table('order_items', function (Blueprint $table) {
            $table->unsignedInteger('shipped_quantity')->default(0)->after('quantity');
            $table->unsignedInteger('cancelled_quantity')->default(0)->after('shipped_quantity');
            $table->string('production_status')->default('not_required')->after('cancelled_quantity');
            $table->string('artwork_url')->nullable()->after('production_status');
            $table->boolean('artwork_approved')->default(false)->after('artwork_url');
            $table->text('production_notes')->nullable()->after('artwork_approved');
            $table->timestamp('production_completed_at')->nullable()->after('production_notes');
        });

        // products — controle de produção e arte por produto
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('requires_production')->default(false)->after('meta');
            $table->boolean('requires_artwork')->default(false)->after('requires_production');
        });

        // índices para bipagem rápida por EAN
        Schema::table('products', function (Blueprint $table) {
            $table->index('ean_gtin', 'products_ean_gtin_idx');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->index('ean_gtin', 'product_variants_ean_gtin_idx');
        });

        // marketplace_accounts — vínculos com webmania e melhor envios
        Schema::table('marketplace_accounts', function (Blueprint $table) {
            $table->foreignId('webmania_account_id')
                ->nullable()
                ->after('meta')
                ->constrained('webmania_accounts')
                ->nullOnDelete();

            $table->foreignId('melhor_envios_account_id')
                ->nullable()
                ->after('webmania_account_id')
                ->constrained('melhor_envios_accounts')
                ->nullOnDelete();
        });

        // webmania_accounts — campos API 2.0, configurações padrão de emissão
        Schema::table('webmania_accounts', function (Blueprint $table) {
            $table->text('bearer_token')->nullable()->after('access_token_secret'); // API 2.0 NFS-e
            $table->string('name')->nullable()->after('company_id');
            $table->string('default_tax_class')->nullable()->after('default_cfop');
            $table->string('default_ncm')->nullable()->after('default_tax_class');
            $table->string('default_cest')->nullable()->after('default_ncm');
            $table->string('default_nature_operation')->default('Venda')->after('default_cest');
            $table->string('default_origin')->default('0')->after('default_nature_operation');
            $table->string('default_shipping_modality')->default('9')->after('default_origin');
            $table->string('intermediador_type')->default('0')->after('default_shipping_modality');
            $table->string('intermediador_cnpj')->nullable()->after('intermediador_type');
            $table->string('intermediador_id')->nullable()->after('intermediador_cnpj');
            $table->text('additional_info_fisco')->nullable()->after('intermediador_id');
            $table->text('additional_info_consumer')->nullable()->after('additional_info_fisco');
            $table->string('auto_emit_trigger')->default('none')->after('additional_info_consumer'); // none|processing|completed
            $table->boolean('auto_send_email')->default(false)->after('auto_emit_trigger');
            $table->boolean('emit_with_order_date')->default(false)->after('auto_send_email');
            $table->string('error_email')->nullable()->after('emit_with_order_date');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('pipeline_status');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn([
                'shipped_quantity', 'cancelled_quantity', 'production_status',
                'artwork_url', 'artwork_approved', 'production_notes', 'production_completed_at',
            ]);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_ean_gtin_idx');
            $table->dropColumn(['requires_production', 'requires_artwork']);
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropIndex('product_variants_ean_gtin_idx');
        });

        Schema::table('marketplace_accounts', function (Blueprint $table) {
            $table->dropForeign(['webmania_account_id']);
            $table->dropForeign(['melhor_envios_account_id']);
            $table->dropColumn(['webmania_account_id', 'melhor_envios_account_id']);
        });

        Schema::table('webmania_accounts', function (Blueprint $table) {
            $table->dropColumn([
                'bearer_token', 'name', 'default_tax_class', 'default_ncm', 'default_cest',
                'default_nature_operation', 'default_origin', 'default_shipping_modality',
                'intermediador_type', 'intermediador_cnpj', 'intermediador_id',
                'additional_info_fisco', 'additional_info_consumer',
                'auto_emit_trigger', 'auto_send_email', 'emit_with_order_date', 'error_email',
            ]);
        });
    }
};
