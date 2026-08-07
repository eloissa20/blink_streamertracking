<?php

namespace App\Http\Controllers;

use App\Models\MusicatConnection;
use App\Services\MusicatPlayRecordSyncer;
use App\Services\MusicatService;
use Illuminate\Http\Request;
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

        $musicatUserId = $profile['id'] ?? $profile['userId'] ?? $request->musicat_handle;

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

        $this->syncer->sync($connection);

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

    /** Manually trigger a re-sync of recently played Apple Music data. */
    public function sync(Request $request)
    {
        $connection = $request->user()->musicatConnection;

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
