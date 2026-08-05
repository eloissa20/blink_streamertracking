<?php

namespace App\Http\Controllers;

use App\Models\StatsFmConnection;
use App\Services\PlayRecordSyncer;
use App\Services\StatsFmService;
use Illuminate\Http\Request;
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
     */
    public function connect(Request $request)
    {
        $user = $request->user();

        if ($user->hasConnectedStatsFm()) {
            return response()->json([
                'message' => 'You already have a connected Stats.fm account. Disconnect it first to link a different one.',
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'statsfm_handle' => ['required', 'string', 'max:100'],
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
            'connected_at' => now(),
        ]);

        $this->syncer->sync($connection);

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

    /** Manually trigger a re-sync of recently played data. */
    public function sync(Request $request)
    {
        $connection = $request->user()->statsFmConnection;

        if (! $connection) {
            return response()->json(['message' => 'No connected account.'], 404);
        }

        $inserted = $this->syncer->sync($connection);

        return response()->json([
            'message' => "Synced. {$inserted} new plays imported.",
            'last_synced_at' => $connection->fresh()->last_synced_at,
        ]);
    }
}
