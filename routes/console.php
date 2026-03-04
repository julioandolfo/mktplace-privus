<?php

use Illuminate\Support\Facades\Schedule;

// Refresh OAuth tokens for marketplace accounts expiring within 30 minutes
Schedule::command('marketplace:refresh-tokens')->everyFifteenMinutes();
