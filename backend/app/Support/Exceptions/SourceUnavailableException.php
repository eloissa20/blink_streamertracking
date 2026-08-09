<?php

namespace App\Support\Exceptions;

/**
 * Thrown when a source (Stats.fm's API, or a Musicat profile render)
 * could not be reached/parsed at all during a sync — as opposed to being
 * reached successfully and simply having nothing new to report.
 *
 * This distinction matters because a sync's "last_synced_at" should only
 * ever be bumped when we genuinely talked to the source. Before this
 * existed, both syncers treated "the request/render failed" and "it
 * succeeded but returned zero items" identically (both surfaced as an
 * empty array), so a sync that silently couldn't even reach the source
 * still got marked as freshly synced and reported success to the user —
 * which is exactly what made a broken Apple Music (Musicat) sync look
 * like it "finished" while the connection's last-synced time never
 * actually moved forward on the next real attempt.
 */
class SourceUnavailableException extends \RuntimeException {}
