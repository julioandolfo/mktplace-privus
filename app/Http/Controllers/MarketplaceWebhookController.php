<?php

namespace App\Http\Controllers;

use App\Enums\MarketplaceType;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MarketplaceWebhookController extends Controller
{
    public function handle(string $type, Request $request): Response
    {
        // Valida que o tipo é um marketplace conhecido
        try {
            MarketplaceType::from($type);
        } catch (\ValueError) {
            return response('', 404);
        }

        // Loga o payload recebido para debug e auditoria
        Log::info("Webhook marketplace [{$type}]", [
            'ip'      => $request->ip(),
            'payload' => $request->all(),
        ]);

        // TODO: processar eventos por tipo (pedidos, estoque, cancelamentos...)
        // Ex: dispatch(new ProcessMarketplaceWebhook($type, $request->all()));

        return response('', 200);
    }
}
