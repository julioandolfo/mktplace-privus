<?php

namespace App\Http\Controllers;

use App\Enums\MarketplaceType;
use App\Jobs\ProcessWebhookEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MarketplaceWebhookController extends Controller
{
    public function handle(string $type, Request $request): Response
    {
        // Validate marketplace type
        try {
            $marketplaceType = MarketplaceType::from($type);
        } catch (\ValueError) {
            return response('', 404);
        }

        $payload = $request->all();

        Log::debug("Webhook [{$type}] recebido", [
            'ip'      => $request->ip(),
            'topic'   => $payload['topic'] ?? null,
            'user_id' => $payload['user_id'] ?? null,
        ]);

        // Dispatch async job to process the event (non-blocking)
        ProcessWebhookEvent::dispatch($type, $payload)->onQueue('high');

        // Always return 200 immediately so ML does not retry
        return response('', 200);
    }
}
