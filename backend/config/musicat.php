<?php

return [
    // Musicat has no public API (confirmed — musicat.fm/<handle> returns an
    // empty JS-only shell to a plain HTTP request, and no api.musicat.fm
    // exists). MusicatService instead renders the public profile page with
    // a real headless browser and reads the rendered DOM — the same page a
    // person would see if they visited musicat.fm/<handle> themselves.
    'profile_base_url' => env('MUSICAT_PROFILE_BASE', 'https://musicat.fm'),

    // Rendering runs on Browserless's hosted /function API (a plain HTTPS
    // call from MusicatService — no Node.js or local Chromium needed on
    // this server, which matters on shared hosting that can't run either).
    // Get a token at browserless.io — the free tier's monthly unit
    // allowance is enough for a periodic sync job. Endpoint region can be
    // changed to whichever Browserless region is closest/fastest for you;
    // see https://docs.browserless.io for the current list.
    'browserless_token' => env('BROWSERLESS_TOKEN'),
    'browserless_endpoint' => env('BROWSERLESS_ENDPOINT', 'https://production-sfo.browserless.io'),

    // How long (ms) to let the page sit after network-idle before reading
    // the DOM, giving the SPA's client-side data fetch time to finish
    // rendering the "Recently played" list. Tune this up if scraped
    // results come back empty/stale.
    'render_delay_ms' => (int) env('MUSICAT_RENDER_DELAY_MS', 2000),

    'sync_interval_minutes' => (int) env('MUSICAT_SYNC_INTERVAL', 10),
];