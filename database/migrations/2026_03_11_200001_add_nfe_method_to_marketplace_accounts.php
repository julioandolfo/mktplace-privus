<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_accounts', function (Blueprint $table) {
            $table->string('nfe_method', 30)->default('webmaniabr')->after('melhor_envios_account_id');
            // Values: 'native' (marketplace handles NF-e), 'webmaniabr' (emit via Webmaniabr), 'both' (native + webmaniabr contingency), 'none' (disabled)
        });
    }

    public function down(): void
    {
        Schema::table('marketplace_accounts', function (Blueprint $table) {
            $table->dropColumn('nfe_method');
        });
    }
};
