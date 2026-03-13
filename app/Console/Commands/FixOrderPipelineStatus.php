<?php

namespace App\Console\Commands;

use App\Enums\OrderStatus;
use App\Enums\PipelineStatus;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;


class FixOrderPipelineStatus extends Command
{
    protected $signature   = 'orders:fix-pipeline';
    protected $description = 'Corrige pipeline_status nulo em pedidos e seta company_id correto';

    public function handle(): int
    {
        // ── 1. Corrige users sem company_id ───────────────────────────────
        $firstCompanyId = DB::table('companies')->orderBy('id')->value('id');
        if ($firstCompanyId) {
            $usersFixed = DB::table('users')->whereNull('company_id')->update(['company_id' => $firstCompanyId]);
            $this->info("Usuários sem company_id corrigidos: {$usersFixed}");
        }

        // ── 2. Pedidos delivered/shipped com pipeline != shipped → corrige ─
        // Isso resolve o caso de pedidos "Entregue" que ainda aparecem como
        // ready_to_ship e inflam os contadores de "Atrasados" na expedição.
        $wrongPipeline = DB::table('orders')
            ->whereIn('status', [
                OrderStatus::Delivered->value,
                OrderStatus::Shipped->value,
                OrderStatus::Returned->value,
            ])
            ->where('pipeline_status', '!=', PipelineStatus::Shipped->value)
            ->orWhere(function ($q) {
                $q->whereIn('status', [
                    OrderStatus::Delivered->value,
                    OrderStatus::Shipped->value,
                    OrderStatus::Returned->value,
                ])->whereNull('pipeline_status');
            })
            ->update(['pipeline_status' => PipelineStatus::Shipped->value]);
        $this->info("Pedidos delivered/shipped com pipeline errado corrigidos: {$wrongPipeline}");

        // ── 3. Pedidos cancelled com pipeline != shipped → marca como shipped ─
        $cancelledFixed = DB::table('orders')
            ->where('status', OrderStatus::Cancelled->value)
            ->where('pipeline_status', '!=', PipelineStatus::Shipped->value)
            ->update(['pipeline_status' => PipelineStatus::Shipped->value]);
        $this->info("Pedidos cancelled com pipeline corrigido: {$cancelledFixed}");

        // ── 4. Pedidos com pipeline_status NULL → define pelo status ───────
        $statusMap = [
            OrderStatus::Cancelled->value    => PipelineStatus::Shipped->value,
            OrderStatus::Delivered->value    => PipelineStatus::Shipped->value,
            OrderStatus::Shipped->value      => PipelineStatus::Shipped->value,
            OrderStatus::ReadyToShip->value  => PipelineStatus::ReadyToShip->value,
            OrderStatus::Confirmed->value    => PipelineStatus::ReadyToShip->value,
            OrderStatus::Pending->value      => PipelineStatus::ReadyToShip->value,
            OrderStatus::InProduction->value => PipelineStatus::InProduction->value,
            OrderStatus::Produced->value     => PipelineStatus::ReadyToShip->value,
            OrderStatus::Returned->value     => PipelineStatus::Shipped->value,
        ];

        $total = 0;
        foreach ($statusMap as $orderStatus => $pipelineStatus) {
            if ($pipelineStatus === null) {
                continue;
            }

            $updated = DB::table('orders')
                ->whereNull('pipeline_status')
                ->where('status', $orderStatus)
                ->update(['pipeline_status' => $pipelineStatus]);

            $total += $updated;
        }
        $this->info("Pedidos com pipeline_status NULL corrigidos: {$total}");

        // ── 5. Corrige orders sem company_id via marketplace_account ───────
        $ordersWithoutCompany = DB::table('orders')
            ->join('marketplace_accounts', 'orders.marketplace_account_id', '=', 'marketplace_accounts.id')
            ->whereNull('orders.company_id')
            ->whereNotNull('marketplace_accounts.company_id')
            ->select('orders.id', 'marketplace_accounts.company_id')
            ->get();

        foreach ($ordersWithoutCompany as $row) {
            DB::table('orders')->where('id', $row->id)->update(['company_id' => $row->company_id]);
        }
        $this->info("Pedidos sem company_id corrigidos via marketplace_account: {$ordersWithoutCompany->count()}");

        // ── 6. Relatório final ─────────────────────────────────────────────
        $this->table(
            ['pipeline_status', 'count'],
            DB::table('orders')
                ->select('pipeline_status', DB::raw('count(*) as total'))
                ->groupBy('pipeline_status')
                ->get()
                ->map(fn ($r) => [$r->pipeline_status ?? 'NULL', $r->total])
                ->toArray()
        );

        $this->info('Concluído!');

        return Command::SUCCESS;
    }
}
