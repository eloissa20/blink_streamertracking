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

    /**
     * All of the authenticated user's Spotify (Stats.fm) connections —
     * there can now be several. Kept under the same response shape
     * (`connected` + a `connections` list) rather than a bare array so an
     * older client that only checks `connected` still degrades sensibly.
     */
    public function index(Request $request)
    {
        $connections = $request->user()->statsFmConnections()->orderBy('connected_at')->get();

        return response()->json([
            'connected' => $connections->isNotEmpty(),
            'connections' => $connections,
            'max_connections' => (int) config('connections.max_statsfm_connections'),
        ]);
    }

    /**
     * Link one additional Stats.fm account to the authenticated user.
     * Unlike the original single-connection version, this no longer
     * rejects the request just because the user already has a
     * connection — only when they're at the configured cap, or when the
     * requested Stats.fm account is already linked to *any* user
     * (including themselves, via the DB-level unique statsfm_user_id).
     */
    public function connect(Request $request)
    {
        $user = $request->user();

        $max = (int) config('connections.max_statsfm_connections');
        if ($user->statsFmConnections()->count() >= $max) {
            return response()->json([
                'message' => "You've reached the limit of {$max} connected Spotify accounts. Disconnect one before adding another.",
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'statsfm_handle' => ['required', 'string', 'max:100'],
            'source' => ['required', 'string', 'in:spotify'],
            'label' => ['nullable', 'string', 'max:60'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $result = $this->connectOne($user, $request->statsfm_handle, $request->label);

        if (! $result['ok']) {
            return response()->json(['message' => $result['message']], $result['status']);
        }

        return response()->json([
            'message' => 'Spotify account connected.',
            'connection' => $result['connection'],
        ], 201);
    }

    /**
     * Link several Stats.fm accounts in one request instead of forcing
     * the user through the single-account form 5+ times. Best-effort per
     * handle: one bad/duplicate/already-linked handle doesn't stop the
     * rest from being attempted. Every handle gets its own result so the
     * UI can show exactly which of the accounts connected and which
     * didn't (and why).
     */
    public function bulkConnect(Request $request)
    {
        $user = $request->user();
        $maxPerRequest = (int) config('connections.max_bulk_connect_per_request');

        $validator = Validator::make($request->all(), [
            'handles' => ['required', 'array', 'min:1', "max:{$maxPerRequest}"],
            'handles.*' => ['required', 'string', 'max:100'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $max = (int) config('connections.max_statsfm_connections');
        $results = [];

        foreach ($request->handles as $handle) {
            if ($user->statsFmConnections()->count() >= $max) {
                $results[] = [
                    'handle' => $handle,
                    'ok' => false,
                    'message' => "Skipped — you've reached the limit of {$max} connected Spotify accounts.",
                ];

                continue;
            }

            $result = $this->connectOne($user, $handle, null);

            $results[] = [
                'handle' => $handle,
                'ok' => $result['ok'],
                'message' => $result['ok'] ? 'Connected.' : $result['message'],
                'connection' => $result['ok'] ? $result['connection'] : null,
            ];
        }

        $connectedCount = collect($results)->where('ok', true)->count();

        return response()->json([
            'message' => "{$connectedCount} of ".count($results).' account(s) connected.',
            'results' => $results,
            'connections' => $user->statsFmConnections()->orderBy('connected_at')->get(),
        ], 200);
    }

    /**
     * Shared connect-one-handle logic used by both connect() and
     * bulkConnect(), so the lookup/duplicate/create/first-sync behavior
     * can't drift between the single and bulk code paths.
     */
    private function connectOne($user, string $handle, ?string $label): array
    {
        $profile = $this->statsFm->findUserByHandle($handle);

        if (! $profile) {
            return [
                'ok' => false,
                'status' => 404,
                'message' => "Could not find a Stats.fm account for \"{$handle}\". Double-check the username and make sure the profile is public.",
            ];
        }

        $existing = StatsFmConnection::where('statsfm_user_id', $profile['id'])->first();
        if ($existing) {
            $message = $existing->user_id === $user->id
                ? "\"{$handle}\" is already one of your connected accounts."
                : "That Stats.fm account is already linked to another user.";

            return ['ok' => false, 'status' => 409, 'message' => $message];
        }

        $connection = StatsFmConnection::create([
            'user_id' => $user->id,
            'statsfm_user_id' => $profile['id'],
            'statsfm_username' => $profile['customId'] ?? $handle,
            'display_name' => $profile['displayName'] ?? null,
            'avatar_url' => $profile['image'] ?? null,
            'connected_source' => 'spotify',
            'label' => $label,
            'connected_at' => now(),
        ]);

        // Best-effort: a first-sync hiccup right after connecting
        // shouldn't undo an otherwise-valid connection (see
        // MusicatController::connect() for the same reasoning).
        //
        // Catches \Throwable, not just SourceUnavailableException: the
        // connection row above is already committed by this point, so
        // ANY exception here (a malformed timestamp from Stats.fm, an
        // unexpected DB constraint, etc.) — not just a down API — must
        // not turn into a 500 for this request. Letting a narrower catch
        // miss one of those was causing a real bug: the account would
        // connect successfully, then this request would still fail with
        // an error, leaving the user staring at "Could not connect that
        // account" for an account that actually *did* connect (only
        // visible as connected after they manually refreshed).
        try {
            $this->syncer->sync($connection);
        } catch (\Throwable $e) {
            Log::warning('Initial Stats.fm sync failed after connect', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
        }

        return ['ok' => true, 'connection' => $connection->fresh()];
    }

    /**
     * Look up one of the user's own connections by id, or null if it
     * doesn't exist / belongs to someone else. Centralized so every
     * per-connection endpoint below enforces ownership the same way,
     * rather than relying on implicit route-model binding (which does
     * not scope to the authenticated user on its own).
     */
    private function ownedConnection(Request $request, int $connectionId): ?StatsFmConnection
    {
        return $request->user()->statsFmConnections()->whereKey($connectionId)->first();
    }

    public function disconnect(Request $request, int $connection)
    {
        $conn = $this->ownedConnection($request, $connection);

        if (! $conn) {
            return response()->json(['message' => 'No such connected account.'], 404);
        }

        $conn->delete();

        return response()->json(['message' => 'Spotify account disconnected.']);
    }

    /**
     * Manually trigger a re-sync of one connection's recently played
     * data. A failed attempt reports itself honestly instead of silently
     * bumping last_synced_at.
     */
    public function sync(Request $request, int $connection)
    {
        $conn = $this->ownedConnection($request, $connection);

        if (! $conn) {
            return response()->json(['message' => 'No such connected account.'], 404);
        }

        try {
            $inserted = $this->syncer->sync($conn);
        } catch (SourceUnavailableException $e) {
            Log::warning('Stats.fm sync failed', [
                'connection_id' => $conn->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => "Couldn't reach Stats.fm to sync right now. Your last successful sync time is unchanged.",
                'synced' => false,
                'last_synced_at' => $conn->last_synced_at,
            ], 502);
        }

        return response()->json([
            'message' => "Synced. {$inserted} new plays imported.",
            'synced' => true,
            'last_synced_at' => $conn->fresh()->last_synced_at,
        ]);
    }

    /**
     * Sync every one of the user's connected Spotify accounts in a
     * single request — the "Sync now" button on the dashboard uses this
     * rather than making the frontend loop over connections itself.
     * Best-effort per connection: one account failing to sync doesn't
     * stop the others.
     */
    public function syncAll(Request $request)
    {
        $connections = $request->user()->statsFmConnections;

        if ($connections->isEmpty()) {
            return response()->json(['message' => 'No connected accounts.'], 404);
        }

        $results = [];
        foreach ($connections as $conn) {
            try {
                $inserted = $this->syncer->sync($conn);
                $results[] = [
                    'connection_id' => $conn->id,
                    'username' => $conn->statsfm_username,
                    'synced' => true,
                    'inserted' => $inserted,
                ];
            } catch (SourceUnavailableException $e) {
                Log::warning('Stats.fm sync failed', [
                    'connection_id' => $conn->id,
                    'error' => $e->getMessage(),
                ]);
                $results[] = [
                    'connection_id' => $conn->id,
                    'username' => $conn->statsfm_username,
                    'synced' => false,
                ];
            }
        }

        $failed = collect($results)->where('synced', false)->count();

        return response()->json([
            'message' => $failed
                ? "Synced with {$failed} account(s) unreachable — try again in a bit."
                : 'All accounts synced.',
            'results' => $results,
        ], $failed ? 207 : 200);
    }
}