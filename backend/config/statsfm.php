<?php

return [
    'base_url' => env('STATSFM_API_BASE', 'https://api.stats.fm/api/v1'),
    'api_key' => env('STATSFM_API_KEY'),
    'sync_interval_minutes' => (int) env('STATSFM_SYNC_INTERVAL', 10),
];
