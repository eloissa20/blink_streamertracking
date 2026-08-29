<?php

namespace App\Http\Controllers;

use App\Models\MusicatConnection;
use App\Services\MusicatPlayRecordSyncer;
use App\Services\MusicatService;
use App\Support\Exceptions\SourceUnavailableException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class MusicatController extends Controller
{
    public function __construct(
        private MusicatService $musicat,
        private MusicatPlayRecordSyncer $syncer,
    ) {}

    public function show(Request $request)
    {
        $connection = $request->user()->musicatConnection;

        if (! $connection) {
            return response()->json(['connected' => false]);
        }

        return response()->json([
            'connected' => true,
            'connection' => $connection,
        ]);
    }

    /**
     * Link a Musicat account to the authenticated user — the exclusive
     * source for Apple Music data in this app. Rejected if the user
     * already has a Musicat connection, or if the requested Musicat
     * account is already linked to a different user.
     */
    public function connect(Request $request)
    {
        $user = $request->user();

        if ($user->hasConnectedMusicat()) {
            return response()->json([
                'message' => "You're already connected to Apple Music via Musicat ({$user->musicatConnection->musicat_username}). Disconnect it first to link a different account.",
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'musicat_handle' => ['required', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $profile = $this->musicat->findUserByHandle($request->musicat_handle);

        if (! $profile) {
            return response()->json([
                'message' => 'Could not find that Musicat account. Double-check the username (musicat.fm/yourusername) and make sure your profile is public.',
            ], 404);
        }

        // Prefer the account's real internal Musicat id (a UUID, scraped
        // off the profile page's own link to its History tab) over the
        // public handle — it's what lets recentlyPlayed() target the full
        // History page instead of the profile's small "Recently played"
        // panel. Falls back to the handle if that link wasn't found on
        // this profile's page for any reason; recentlyPlayed() already
        // knows how to fall back to the old panel scrape when it gets a
        // non-UUID id.
        $musicatUserId = $profile['historyUserId'] ?? $profile['id'] ?? $profile['userId'] ?? $request->musicat_handle;

        $existing = MusicatConnection::where('musicat_user_id', $musicatUserId)->first();
        if ($existing) {
            return response()->json([
                'message' => 'That Musicat account is already linked to another user.',
            ], 409);
        }

        $connection = MusicatConnection::create([
            'user_id' => $user->id,
            'musicat_user_id' => $musicatUserId,
            'musicat_username' => $profile['username'] ?? $profile['customId'] ?? $request->musicat_handle,
            'display_name' => $profile['displayName'] ?? $profile['name'] ?? null,
            'avatar_url' => $profile['avatarUrl'] ?? $profile['image'] ?? null,
            'connected_at' => now(),
        ]);

        // The initial sync right after connecting is a nice-to-have, not a
        // condition of the connection itself succeeding — a profile that
        // fails to render on this first attempt (slow page, transient
        // Musicat/Chromium hiccup) shouldn't undo an otherwise-valid
        // connect. The user can always hit "Sync now" once linked.
        //
        // Catches \Throwable, not just SourceUnavailableException: the
        // connection row above is already committed, so any other
        // exception thrown while scraping/parsing the first sync (not
        // just "couldn't reach it") must not turn this into a 500 —
        // otherwise the account ends up connected in the database while
        // the request still reports failure, and the user only sees it
        // worked after manually refreshing the page.
        try {
            $this->syncer->sync($connection);
        } catch (\Throwable $e) {
            Log::warning('Initial Musicat sync failed after connect', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
        }

        return response()->json([
            'message' => 'Musicat account connected.',
            'connection' => $connection->fresh(),
        ], 201);
    }

    public function disconnect(Request $request)
    {
        $connection = $request->user()->musicatConnection;

        if (! $connection) {
            return response()->json(['message' => 'No connected account.'], 404);
        }

        $connection->delete();

        return response()->json(['message' => 'Musicat account disconnected.']);
    }

    /**
     * Manually trigger a re-sync of recently played Apple Music data.
     *
     * If the Musicat profile can't be reached/rendered this attempt, that
     * is now a real failure response (not a false "Synced." success) —
     * last_synced_at is deliberately left untouched so "Last synced X ago"
     * on the dashboard keeps reflecting the last time a sync actually
     * completed, rather than silently jumping to "just now" for an
     * attempt that fetched nothing.
     */
    public function sync(Request $request)
    {
        $connection = $request->user()->musicatConnection;

        if (! $connection) {
            return response()->json(['message' => 'No connected account.'], 404);
        }

        try {
            $inserted = $this->syncer->sync($connection);
        } catch (SourceUnavailableException $e) {
            Log::warning('Musicat sync failed', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => "Couldn't reach your Musicat profile to sync it — it may be temporarily down, or your profile may have been set to private. Your last successful sync time is unchanged.",
                'synced' => false,
                'last_synced_at' => $connection->last_synced_at,
            ], 502);
        }

        return response()->json([
            'message' => "Synced. {$inserted} new plays imported.",
            'synced' => true,
            'last_synced_at' => $connection->fresh()->last_synced_at,
        ]);
    }
}