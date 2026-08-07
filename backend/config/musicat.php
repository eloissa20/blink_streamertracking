<?php

return [
    // Musicat has no public API (confirmed — musicat.fm/<handle> returns an
    // empty JS-only shell to a plain HTTP request, and no api.musicat.fm
    // exists). MusicatService instead renders the public profile page with
    // a real headless browser (via spatie/browsershot, which wraps Node +
    // Puppeteer/Chromium) and reads the rendered DOM — the same page a
    // person would see if they visited musicat.fm/<handle> themselves.
    'profile_base_url' => env('MUSICAT_PROFILE_BASE', 'https://musicat.fm'),

    // Path to a Chrome/Chromium binary for Browsershot to drive. Leave null
    // to let Browsershot fall back to its bundled/auto-detected Puppeteer
    // Chromium — but on most servers you'll want to install Chromium
    // (e.g. `apt install chromium`) and point this at it explicitly, since
    // Browsershot's auto-download step needs outbound access to Google's
    // CDN, which locked-down servers/CI often block.
    'chrome_path' => env('MUSICAT_CHROME_PATH'),

    // How long (ms) to let the page sit after network-idle before reading
    // the DOM, giving the SPA's client-side data fetch time to finish
    // rendering the "Recently played" list. Tune this up if scraped
    // results come back empty/stale.
    'render_delay_ms' => (int) env('MUSICAT_RENDER_DELAY_MS', 2000),

    'sync_interval_minutes' => (int) env('MUSICAT_SYNC_INTERVAL', 10),
];
