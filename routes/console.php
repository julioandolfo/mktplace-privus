<?php

use Illuminate\Support\Facades\Schedule;

// Refresh OAuth tokens for marketplace accounts expiring within 30 minutes
Schedule::command('marketplace:refresh-tokens')->everyFifteenMinutes();

// Sync orders from Mercado Livre (every 15 minutes, no overlap)
Schedule::command('marketplace:sync-orders --days=1')->everyFifteenMinutes()->withoutOverlapping(10);

// Sync listings/products from Mercado Livre (every hour, no overlap)
Schedule::command('marketplace:sync-listings')->hourly()->withoutOverlapping(30);

// Sync listing quality scores from Mercado Livre (every 6 hours)
Schedule::command('marketplace:sync-quality')->everySixHours()->withoutOverlapping(30);

// Sync post-sale messages from Mercado Livre (every 15 minutes, no overlap)
Schedule::command('marketplace:sync-messages')->everyFifteenMinutes()->withoutOverlapping(10);
