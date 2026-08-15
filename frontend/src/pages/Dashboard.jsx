import { useEffect, useState, useCallback, useMemo } from 'react';
import { motion } from 'framer-motion';
import api from '../api/client';
import { useAuth } from '../lib/AuthContext';
import Section from '../components/Section';
import TimeFilter from '../components/TimeFilter';
import StatListCard from '../components/StatListCard';
import RecentlyPlayedTable from '../components/RecentlyPlayedTable';
import ActivityChart from '../components/ActivityChart';
import HeaderClock from '../components/HeaderClock';
import Waveform from '../components/Waveform';
import SpotifyAccountSwitcher from '../components/SpotifyAccountSwitcher';

// Remembers which of the user's Spotify accounts was last active across a
// page reload. Purely a UI convenience — the backend still defaults to the
// same (earliest-connected) account on its own if this is ever missing or
// stale, so losing this value never breaks anything, it just resets which
// account shows first.
const ACTIVE_CONNECTION_KEY = 'active_statsfm_connection_id';

export default function Dashboard() {
  const { user } = useAuth();
  const [window_, setWindow] = useState('week');
  const [tracks, setTracks] = useState([]);
  const [artists, setArtists] = useState([]);
  const [recent, setRecent] = useState([]);
  const [dailyActivity, setDailyActivity] = useState([]);
  const [lastSyncedAt, setLastSyncedAt] = useState(null);
  const [loading, setLoading] = useState(true);
  const [syncing, setSyncing] = useState(false);
  const [syncError, setSyncError] = useState(null);

  // --- Multiple connected Spotify accounts -----------------------------
  const [spotifyConnections, setSpotifyConnections] = useState([]);
  const [activeConnectionId, setActiveConnectionId] = useState(() => {
    const stored = Number(localStorage.getItem(ACTIVE_CONNECTION_KEY));
    return Number.isFinite(stored) && stored > 0 ? stored : null;
  });

  const handleSwitchAccount = (id) => {
    setActiveConnectionId(id);
    localStorage.setItem(ACTIVE_CONNECTION_KEY, String(id));
  };

  const load = useCallback(
    async (win, connectionId) => {
      setLoading(true);
      const statsParams = { window: win };
      if (connectionId) statsParams.statsfm_connection_id = connectionId;
      const recentParams = connectionId ? { statsfm_connection_id: connectionId } : {};

      const [tr, ar, rp, da, sf, mcConn] = await Promise.all([
        api.get('/me/top-tracks', { params: statsParams }),
        api.get('/me/top-artists', { params: statsParams }),
        api.get('/me/recently-played', { params: recentParams }),
        api.get('/me/daily-activity', { params: recentParams }),
        api.get('/statsfm/connections'),
        api.get('/musicat/connection'),
      ]);
      setTracks(tr.data.tracks);
      setArtists(ar.data.artists);
      setRecent(rp.data.recently_played);
      setDailyActivity(da.data.days);
      setSpotifyConnections(sf.data.connections ?? []);

      // If we don't have an active connection selected yet (first load, or
      // the previously-remembered one no longer exists), fall back to
      // whichever connection the backend actually used for this response
      // so the switcher and the data on screen always agree.
      const stillExists = sf.data.connections?.some((c) => c.id === connectionId);
      if (!stillExists) {
        const fallback = tr.data.statsfm_connection_id ?? sf.data.connections?.[0]?.id ?? null;
        if (fallback) {
          setActiveConnectionId(fallback);
          localStorage.setItem(ACTIVE_CONNECTION_KEY, String(fallback));
        }
      }

      // Show whichever connected source synced most recently — the active
      // Spotify connection, plus Apple Music if that's connected too.
      const activeSpotifyConn = sf.data.connections?.find((c) => c.id === connectionId);
      const timestamps = [
        activeSpotifyConn?.last_synced_at,
        mcConn.data.connection?.last_synced_at,
      ].filter(Boolean);
      setLastSyncedAt(timestamps.length ? timestamps.sort().at(-1) : null);
      setLoading(false);
    },
    []
  );

  useEffect(() => {
    load(window_, activeConnectionId);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [window_, activeConnectionId]);

  const spotifyRecent = useMemo(
    () => recent.filter((item) => item.source !== 'apple_music'),
    [recent]
  );
  const appleMusicRecent = useMemo(
    () => recent.filter((item) => item.source === 'apple_music'),
    [recent]
  );

  const handleSync = async () => {
    setSyncing(true);
    setSyncError(null);

    // Sync every connected Spotify account plus Apple Music, not just the
    // one currently being viewed — "Sync now" should refresh all of a
    // user's connections in one go rather than requiring them to switch
    // to each account individually first. allSettled on purpose: one
    // source failing shouldn't stop the others from syncing or from us
    // reloading whatever did succeed.
    const [sf, mc] = await Promise.allSettled([
      api.post('/statsfm/sync'),
      api.post('/musicat/sync'),
    ]);

    const failed = [];
    if (sf.status === 'rejected' && sf.reason?.response?.status !== 404) {
      failed.push('Spotify');
    }
    if (mc.status === 'rejected' && mc.reason?.response?.status !== 404) {
      failed.push('Apple Music');
    }
    if (failed.length) {
      const detail =
        mc.status === 'rejected' && mc.reason?.response?.data?.message
          ? mc.reason.response.data.message
          : sf.status === 'rejected' && sf.reason?.response?.data?.message
          ? sf.reason.response.data.message
          : null;
      setSyncError(
        `Couldn't sync ${failed.join(' and ')} — try again in a bit.${detail ? ` (${detail})` : ''}`
      );
    }

    try {
      await load(window_, activeConnectionId);
    } finally {
      setSyncing(false);
    }
  };

  return (
    <div className="max-w-6xl mx-auto px-4 sm:px-6 pb-24 pt-8 sm:pt-12">
      <motion.div
        initial={{ opacity: 0, y: 10 }}
        animate={{ opacity: 1, y: 0 }}
        className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8"
      >
        <div>
          <p className="text-xs uppercase tracking-[0.2em] text-violet-bright font-semibold mb-1">
            Personal View
          </p>
          <h1 className="font-display text-2xl sm:text-3xl font-semibold text-white">
            Hey {user?.name?.split(' ')[0] ?? 'there'}, here's your sound
          </h1>
          <HeaderClock lastSyncedAt={lastSyncedAt} />
        </div>
        <div className="flex flex-col items-end gap-1.5">
          <div className="flex flex-wrap items-center gap-3">
            <SpotifyAccountSwitcher
              connections={spotifyConnections}
              activeId={activeConnectionId}
              onChange={handleSwitchAccount}
            />
            <TimeFilter value={window_} onChange={setWindow} />
            <button
              onClick={handleSync}
              disabled={syncing}
              className="text-sm px-4 py-2 rounded-full border border-white/10 hover:border-white/30 transition-colors disabled:opacity-50 whitespace-nowrap"
            >
              {syncing ? 'Syncing…' : 'Sync now'}
            </button>
          </div>
          {syncError && (
            <p className="text-xs text-red-400 max-w-xs text-right">{syncError}</p>
          )}
        </div>
      </motion.div>

      {loading ? (
        <div className="flex justify-center py-24">
          <Waveform bars={5} className="scale-150" />
        </div>
      ) : (
        <div className="flex flex-col gap-6">
          <div className="grid md:grid-cols-2 gap-4 sm:gap-6">
            <StatListCard
              title="Top Tracks"
              items={tracks}
              emptyLabel="Nothing played in this window yet."
              renderPrimary={(t) => t.track_name}
              renderMetric={(t) => `${Number(t.play_count).toLocaleString()} play${t.play_count === 1 ? '' : 's'}`}
              renderImage={(t) => t.artwork_url}
              imageShape="square"
            />
            <StatListCard
              title="Top Artists"
              items={artists}
              emptyLabel="Nothing played in this window yet."
              renderPrimary={(a) => a.artist_name}
              renderMetric={(a) => `${Number(a.play_count).toLocaleString()} streams`}
              renderImage={(a) => a.artist_image_url}
              imageShape="circle"
            />
          </div>

          <div>
            <p className="text-xs uppercase tracking-[0.2em] text-violet-bright font-semibold mb-1">
              Live
            </p>
            <h2 className="font-display text-2xl font-semibold text-white mb-4">
              Recently Played
            </h2>
            <div className="grid md:grid-cols-2 gap-4 sm:gap-6">
              <div>
                <p className="text-sm font-medium text-mist mb-3">Spotify</p>
                <RecentlyPlayedTable
                  items={spotifyRecent}
                  emptyLabel='No recent Spotify plays synced yet — hit "Sync now".'
                />
              </div>
              <div>
                <p className="text-sm font-medium text-mist mb-3">Apple Music</p>
                <RecentlyPlayedTable
                  items={appleMusicRecent}
                  emptyLabel='No recent Apple Music plays synced yet — hit "Sync now".'
                />
              </div>
            </div>
          </div>
        </div>
      )}

      {!loading && (
        <div className="mt-6">
          <ActivityChart days={dailyActivity} />
        </div>
      )}
    </div>
  );
}
