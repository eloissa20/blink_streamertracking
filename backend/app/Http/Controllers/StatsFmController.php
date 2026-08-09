<?php

namespace App\Http\Controllers;

use App\Models\StatsFmConnection;
use App\Services\PlayRecordSyncer;
use App\Services\StatsFmService;
use App\Support\Exceptions\SourceUnavailableException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class StatsFmController extends Controller
{
    public function __construct(
        private StatsFmService $statsFm,
        private PlayRecordSyncer $syncer,
    ) {}

    public function show(Request $request)
    {
        $connection = $request->user()->statsFmConnection;

        if (! $connection) {
            return response()->json(['connected' => false]);
        }

        return response()->json([
            'connected' => true,
            'connection' => $connection,
        ]);
    }

    /**
     * Link a Stats.fm account to the authenticated user.
     * Rejected if the user already has a connection, or if the requested
     * Stats.fm account is already linked to a different user.
     *
     * Stats.fm connections are Spotify-only now — Apple Music is tracked
     * exclusively through Musicat (see MusicatController). A Musicat
     * connection is independent of this one, so a user can have both.
     */
    public function connect(Request $request)
    {
        $user = $request->user();

        if ($user->hasConnectedStatsFm()) {
            return response()->json([
                'message' => "You're already connected to Spotify via Stats.fm ({$user->statsFmConnection->statsfm_username}). Disconnect it first to link a different account.",
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'statsfm_handle' => ['required', 'string', 'max:100'],
            'source' => ['required', 'string', 'in:spotify'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $profile = $this->statsFm->findUserByHandle($request->statsfm_handle);

        if (! $profile) {
            return response()->json([
                'message' => 'Could not find that Stats.fm account. Double-check the username and make sure your Stats.fm profile is public.',
            ], 404);
        }

        $existing = StatsFmConnection::where('statsfm_user_id', $profile['id'])->first();
        if ($existing) {
            return response()->json([
                'message' => 'That Stats.fm account is already linked to another user.',
            ], 409);
        }

        $connection = StatsFmConnection::create([
            'user_id' => $user->id,
            'statsfm_user_id' => $profile['id'],
            'statsfm_username' => $profile['customId'] ?? $request->statsfm_handle,
            'display_name' => $profile['displayName'] ?? null,
            'avatar_url' => $profile['image'] ?? null,
            'connected_source' => $request->source,
            'connected_at' => now(),
        ]);

        // See MusicatController::connect() for why this stays best-effort:
        // a first-sync hiccup right after connecting shouldn't undo an
        // otherwise-valid connection.
        try {
            $this->syncer->sync($connection);
        } catch (SourceUnavailableException $e) {
            Log::warning('Initial Stats.fm sync failed after connect', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Stats.fm account connected.',
            'connection' => $connection->fresh(),
        ], 201);
    }

    public function disconnect(Request $request)
    {
        $connection = $request->user()->statsFmConnection;

        if (! $connection) {
            return response()->json(['message' => 'No connected account.'], 404);
        }

        $connection->delete();

        return response()->json(['message' => 'Stats.fm account disconnected.']);
    }

    /**
     * Manually trigger a re-sync of recently played data. See the
     * matching Musicat::sync() docblock — a failed attempt now reports
     * itself honestly instead of silently bumping last_synced_at.
     */
    public function sync(Request $request)
    {
        $connection = $request->user()->statsFmConnection;

        if (! $connection) {
            return response()->json(['message' => 'No connected account.'], 404);
        }

        try {
            $inserted = $this->syncer->sync($connection);
        } catch (SourceUnavailableException $e) {
            Log::warning('Stats.fm sync failed', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => "Couldn't reach Stats.fm to sync right now. Your last successful sync time is unchanged.",
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
