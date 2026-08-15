<?php

return [
    // Ceiling on how many Stats.fm (Spotify) connections one system user
    // may link at the same time. The feature exists specifically so a
    // user can link 5+ accounts, so the default sits comfortably above
    // that rather than right at it — but it's still capped, both to keep
    // a single sync/bulk-connect request bounded and to make runaway
    // account-linking (e.g. a compromised token spraying connect calls)
    // hit a wall instead of growing without limit.
    'max_statsfm_connections' => (int) env('MAX_STATSFM_CONNECTIONS', 15),

    // Cap on how many handles a single bulk-connect request may attempt
    // at once, independent of how many the user already has connected.
    // Keeps one request from being able to hammer the Stats.fm lookup
    // API an unbounded number of times.
    'max_bulk_connect_per_request' => (int) env('MAX_BULK_CONNECT_PER_REQUEST', 10),
];