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
        // Corrige users sem company_id (pega a primeira empresa disponível)
        $firstCompanyId = DB::table('companies')->orderBy('id')->value('id');
        if ($firstCompanyId) {
            $usersFixed = DB::table('users')->whereNull('company_id')->update(['company_id' => $firstCompanyId]);
            $this->info("Usuários sem company_id corrigidos: {$usersFixed}");
        }

        // Mapeia OrderStatus → PipelineStatus para orders sem pipeline_status
        $statusMap = [
            OrderStatus::Cancelled->value    => null, // manter null (cancelado)
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

        // Pedidos com pipeline = 'shipped' mas status = 'cancelled' → corrige para não aparecer
        DB::table('orders')
            ->whereNull('pipeline_status')
            ->where('status', OrderStatus::Cancelled->value)
            ->update(['pipeline_status' => 'cancelled_pipeline']);

        $this->info("Pedidos com pipeline_status corrigidos: {$total}");

        // Corrige orders sem company_id (se o marketplace_account tiver company_id)
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

        // Relatório final
        $ready = Order::where('pipeline_status', PipelineStatus::ReadyToShip->value)->count();
        $null  = Order::whereNull('pipeline_status')->count();
        $this->table(
            ['pipeline_status', 'count'],
            array_merge(
                DB::table('orders')->select('pipeline_status', DB::raw('count(*) as total'))->groupBy('pipeline_status')->get()->map(fn ($r) => [$r->pipeline_status ?? 'NULL', $r->total])->toArray(),
            )
        );

        $this->info('Concluído!');

        return Command::SUCCESS;
    }
}
