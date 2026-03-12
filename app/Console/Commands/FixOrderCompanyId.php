<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixOrderCompanyId extends Command
{
    protected $signature   = 'orders:fix-company-id';
    protected $description = 'Verifica e corrige company_id dos pedidos com base na marketplace_account';

    public function handle(): int
    {
        // ── 1. Diagnóstico ───────────────────────────────────────────────
        $withoutCompany = DB::table('orders')
            ->whereNull('company_id')
            ->count();

        $divergent = DB::table('orders')
            ->join('marketplace_accounts', 'orders.marketplace_account_id', '=', 'marketplace_accounts.id')
            ->whereColumn('orders.company_id', '!=', 'marketplace_accounts.company_id')
            ->count();

        $this->info("Pedidos sem company_id: {$withoutCompany}");
        $this->info("Pedidos com company_id divergente: {$divergent}");

        if ($withoutCompany === 0 && $divergent === 0) {
            $this->info('Todos os pedidos estão com company_id correto!');
            return Command::SUCCESS;
        }

        // ── 2. Detalhes dos divergentes ──────────────────────────────────
        if ($divergent > 0) {
            $samples = DB::table('orders')
                ->join('marketplace_accounts', 'orders.marketplace_account_id', '=', 'marketplace_accounts.id')
                ->whereColumn('orders.company_id', '!=', 'marketplace_accounts.company_id')
                ->select(
                    'orders.id',
                    'orders.external_id',
                    'orders.company_id as order_company_id',
                    'marketplace_accounts.company_id as account_company_id',
                    'marketplace_accounts.name as account_name',
                )
                ->limit(20)
                ->get();

            $this->table(
                ['Order ID', 'External ID', 'Order Company', 'Account Company', 'Account'],
                $samples->map(fn ($r) => [
                    $r->id,
                    $r->external_id,
                    $r->order_company_id,
                    $r->account_company_id,
                    $r->account_name,
                ])->toArray()
            );
        }

        // ── 3. Correção ─────────────────────────────────────────────────
        if (! $this->confirm('Deseja corrigir os pedidos?', true)) {
            $this->info('Operação cancelada.');
            return Command::SUCCESS;
        }

        $fixedNull = DB::table('orders')
            ->join('marketplace_accounts', 'orders.marketplace_account_id', '=', 'marketplace_accounts.id')
            ->whereNull('orders.company_id')
            ->whereNotNull('marketplace_accounts.company_id')
            ->update(['orders.company_id' => DB::raw('marketplace_accounts.company_id')]);

        $fixedDivergent = DB::table('orders')
            ->join('marketplace_accounts', 'orders.marketplace_account_id', '=', 'marketplace_accounts.id')
            ->whereColumn('orders.company_id', '!=', 'marketplace_accounts.company_id')
            ->update(['orders.company_id' => DB::raw('marketplace_accounts.company_id')]);

        $this->info("Pedidos sem company_id corrigidos: {$fixedNull}");
        $this->info("Pedidos com company_id divergente corrigidos: {$fixedDivergent}");

        // ── 4. Relatório final ───────────────────────────────────────────
        $this->table(
            ['Company ID', 'Total Pedidos'],
            DB::table('orders')
                ->select('company_id', DB::raw('count(*) as total'))
                ->groupBy('company_id')
                ->orderBy('company_id')
                ->get()
                ->map(fn ($r) => [$r->company_id ?? 'NULL', $r->total])
                ->toArray()
        );

        $this->info('Concluído!');

        return Command::SUCCESS;
    }
}
