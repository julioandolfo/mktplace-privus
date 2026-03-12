<?php

use Illuminate\Support\Facades\Schedule;

// Refresh OAuth tokens for marketplace accounts expiring within 30 minutes
Schedule::command('marketplace:refresh-tokens')->everyFifteenMinutes();

// Sync orders from Mercado Livre (every 5 minutes)
Schedule::command('marketplace:sync-orders')->everyFiveMinutes();

// Sync listings/products from Mercado Livre (every hour)
Schedule::command('marketplace:sync-listings')->hourly();

// Sync listing quality scores from Mercado Livre (every 6 hours)
Schedule::command('marketplace:sync-quality')->everySixHours();

// Sync post-sale messages from Mercado Livre (every 5 minutes)
Schedule::command('marketplace:sync-messages')->everyFiveMinutes();
