import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import api from '../api/client';
import { useAuth } from '../lib/AuthContext';
import Waveform from '../components/Waveform';
import { SpotifyIcon, AppleMusicIcon } from '../components/BrandIcons';

const SPOTIFY_ACCENT = { backgroundColor: '#1DB954', color: '#000' };

/**
 * The Spotify panel now manages a *list* of connections instead of a
 * single one: existing accounts (each independently disconnectable), a
 * quick-add for one more, and a bulk-add box so linking 5+ accounts
 * doesn't mean repeating the single-account form five separate times.
 */
function SpotifyPanel({ connections, isLoading, maxConnections, onAdded, onRemoved }) {
  const [handle, setHandle] = useState('');
  const [error, setError] = useState(null);
  const [busyId, setBusyId] = useState(null); // connection id currently being disconnected, or 'connect'

  const [bulkOpen, setBulkOpen] = useState(false);
  const [bulkText, setBulkText] = useState('');
  const [bulkBusy, setBulkBusy] = useState(false);
  const [bulkResults, setBulkResults] = useState(null);

  const atLimit = maxConnections != null && connections.length >= maxConnections;

  const connectOne = async (e) => {
    e.preventDefault();
    if (!handle) return;
    setError(null);
    setBusyId('connect');
    try {
      await api.post('/statsfm/connect', { statsfm_handle: handle, source: 'spotify' });
      setHandle('');
      await onAdded();
    } catch (err) {
      setError(err.response?.data?.message || 'Could not connect that account.');
    } finally {
      setBusyId(null);
    }
  };

  const disconnect = async (id) => {
    setError(null);
    setBusyId(id);
    try {
      await api.delete(`/statsfm/connections/${id}`);
      await onRemoved();
    } catch (err) {
      setError(err.response?.data?.message || 'Could not disconnect that account.');
    } finally {
      setBusyId(null);
    }
  };

  const connectBulk = async (e) => {
    e.preventDefault();
    const handles = bulkText
      .split(/[\n,]/)
      .map((h) => h.trim())
      .filter(Boolean);
    if (!handles.length) return;

    setBulkBusy(true);
    setBulkResults(null);
    setError(null);
    try {
      const { data } = await api.post('/statsfm/connect/bulk', { handles });
      setBulkResults(data.results);
      setBulkText('');
      await onAdded();
    } catch (err) {
      setError(err.response?.data?.message || 'Could not connect those accounts.');
    } finally {
      setBulkBusy(false);
    }
  };

  return (
    <div className="glass-card rounded-3xl p-6 sm:p-8">
      <div className="flex items-center justify-between gap-3 mb-1">
        <div className="flex items-center gap-3">
          <SpotifyIcon className="w-5 h-5 text-spotify" />
          <h2 className="font-display text-xl font-semibold text-white">Spotify</h2>
        </div>
        {connections.length > 0 && (
          <span className="text-xs text-mist">
            {connections.length}{maxConnections ? ` / ${maxConnections}` : ''} connected
          </span>
        )}
      </div>
      <p className="text-sm text-mist mb-6">
        Tracked via Stats.fm. Connect more than one Spotify account — each keeps its own
        completely separate Recently Played, Top Tracks, and Top Artists.
      </p>

      {isLoading && (
        <div className="flex justify-center py-8">
          <Waveform bars={5} />
        </div>
      )}

      {!isLoading && connections.length > 0 && (
        <div className="flex flex-col gap-2 mb-5">
          {connections.map((c) => (
            <div
              key={c.id}
              className="flex items-center gap-3 rounded-xl border border-white/10 px-4 py-3"
            >
              <SpotifyIcon className="w-5 h-5 text-spotify shrink-0" />
              <div className="min-w-0 flex-1">
                <p className="text-sm font-medium text-white truncate">
                  {c.label || c.statsfm_username}
                </p>
                <p className="text-xs text-mist truncate">@{c.statsfm_username}</p>
              </div>
              <button
                onClick={() => disconnect(c.id)}
                disabled={busyId === c.id}
                className="text-xs px-3 py-1.5 rounded-full border border-white/10 hover:border-apple/50 hover:text-apple transition-colors disabled:opacity-50 whitespace-nowrap"
              >
                {busyId === c.id ? 'Removing…' : 'Disconnect'}
              </button>
            </div>
          ))}
        </div>
      )}

      {error && <p className="text-sm text-apple mb-4">{error}</p>}

      {!isLoading && !atLimit && (
        <>
          <form className="flex flex-col gap-3 sm:flex-row" onSubmit={connectOne}>
            <input
              type="text"
              placeholder="Stats.fm username"
              value={handle}
              onChange={(e) => setHandle(e.target.value)}
              className="flex-1 bg-ink-surface border border-ink-border rounded-xl px-4 py-3 text-sm outline-none focus:border-violet transition-colors"
            />
            <button
              type="submit"
              disabled={busyId === 'connect' || !handle}
              style={SPOTIFY_ACCENT}
              className="flex items-center justify-center gap-2 rounded-xl px-5 py-3 font-medium hover:opacity-90 transition-opacity disabled:opacity-50 whitespace-nowrap"
            >
              {busyId === 'connect' ? 'Connecting…' : connections.length ? 'Add account' : 'Connect'}
            </button>
          </form>

          <button
            type="button"
            onClick={() => setBulkOpen((v) => !v)}
            className="text-xs text-mist hover:text-white transition-colors mt-3"
          >
            {bulkOpen ? 'Hide bulk add' : 'Connect several at once →'}
          </button>

          {bulkOpen && (
            <form onSubmit={connectBulk} className="mt-3 flex flex-col gap-3">
              <textarea
                rows={4}
                placeholder={'One Stats.fm username per line (or comma-separated)\ne.g.\njane.dc\njane.alt\njane.gaming'}
                value={bulkText}
                onChange={(e) => setBulkText(e.target.value)}
                className="bg-ink-surface border border-ink-border rounded-xl px-4 py-3 text-sm outline-none focus:border-violet transition-colors resize-none"
              />
              <button
                type="submit"
                disabled={bulkBusy || !bulkText.trim()}
                className="rounded-xl py-2.5 font-medium border border-white/10 hover:border-white/30 transition-colors disabled:opacity-50"
              >
                {bulkBusy ? 'Connecting all…' : 'Connect all'}
              </button>
              {bulkResults && (
                <ul className="text-xs text-mist flex flex-col gap-1">
                  {bulkResults.map((r) => (
                    <li key={r.handle} className={r.ok ? 'text-green-400' : 'text-apple'}>
                      {r.handle}: {r.message}
                    </li>
                  ))}
                </ul>
              )}
            </form>
          )}
        </>
      )}

      {atLimit && (
        <p className="text-xs text-mist">
          You've reached the limit of {maxConnections} connected accounts. Disconnect one to add another.
        </p>
      )}

      <p className="text-xs text-mist mt-5">
        Don't have Stats.fm yet? Install it, link Spotify, make your profile public, then come
        back here with your username.
      </p>
    </div>
  );
}

function AppleMusicPanel({ connection, isLoading, handle, setHandle, error, busy, onConnect, onDisconnect }) {
  return (
    <div className="glass-card rounded-3xl p-6 sm:p-8">
      <div className="flex items-center gap-3 mb-1">
        <AppleMusicIcon className="w-5 h-5 text-apple" />
        <h2 className="font-display text-xl font-semibold text-white">Apple Music</h2>
      </div>
      <p className="text-sm text-mist mb-6">Tracked via Musicat</p>

      {isLoading && (
        <div className="flex justify-center py-8">
          <Waveform bars={5} />
        </div>
      )}

      {!isLoading && connection && (
        <>
          <div className="flex items-center gap-3 rounded-xl border border-white/10 px-4 py-3 mb-4">
            <AppleMusicIcon className="w-5 h-5 text-apple" />
            <div className="min-w-0">
              <p className="text-sm font-medium text-white truncate">Connected</p>
              <p className="text-xs text-mist truncate">{connection.username}</p>
            </div>
          </div>
          {error && <p className="text-sm text-apple mb-4">{error}</p>}
          <button
            onClick={onDisconnect}
            disabled={!!busy}
            className="w-full rounded-xl py-3 font-medium border border-white/10 hover:border-apple/50 hover:text-apple transition-colors disabled:opacity-50"
          >
            {busy === 'disconnect' ? 'Disconnecting…' : 'Disconnect'}
          </button>
        </>
      )}

      {!isLoading && !connection && (
        <form className="flex flex-col gap-4" onSubmit={onConnect}>
          <input
            type="text"
            required
            placeholder="Your Musicat username"
            value={handle}
            onChange={(e) => setHandle(e.target.value)}
            className="bg-ink-surface border border-ink-border rounded-xl px-4 py-3 text-sm outline-none focus:border-violet transition-colors"
          />
          {error && <p className="text-sm text-apple">{error}</p>}
          <button
            type="submit"
            disabled={!!busy}
            style={{
              backgroundImage: 'linear-gradient(120deg, #FA2D48 0%, #FB5C74 100%)',
              color: '#fff',
            }}
            className="flex items-center justify-center gap-2 rounded-xl py-3 font-medium hover:opacity-90 transition-opacity disabled:opacity-50"
          >
            <AppleMusicIcon className="w-5 h-5" />
            {busy === 'connect' ? 'Connecting…' : 'Connect'}
          </button>
        </form>
      )}

      <p className="text-xs text-mist mt-5">
        Don't have Musicat yet? Install it from musicat.fm, link Apple Music, make your profile
        public, then come back here with your username (the part after musicat.fm/).
      </p>
    </div>
  );
}

export default function Connect() {
  const { refresh } = useAuth();
  const navigate = useNavigate();

  // --- Stats.fm (Spotify) — now a list --------------------------------
  const [sfConnections, setSfConnections] = useState(undefined);
  const [sfMax, setSfMax] = useState(null);

  // --- Musicat (Apple Music) ------------------------------------------
  const [mcHandle, setMcHandle] = useState('');
  const [mcError, setMcError] = useState(null);
  const [mcBusy, setMcBusy] = useState(null);
  const [mcConnection, setMcConnection] = useState(undefined);

  const loadConnections = async () => {
    const [sf, mc] = await Promise.all([
      api.get('/statsfm/connections'),
      api.get('/musicat/connection'),
    ]);
    setSfConnections(sf.data.connections ?? []);
    setSfMax(sf.data.max_connections ?? null);
    setMcConnection(
      mc.data.connected ? { ...mc.data.connection, username: mc.data.connection.musicat_username } : null
    );
  };

  useEffect(() => {
    loadConnections();
  }, []);

  const handleSpotifyAdded = async () => {
    await refresh();
    await loadConnections();
    navigate('/dashboard');
  };

  const handleSpotifyRemoved = async () => {
    await refresh();
    await loadConnections();
  };

  const connectMusicat = async (e) => {
    e.preventDefault();
    if (!mcHandle) return;
    setMcError(null);
    setMcBusy('connect');
    try {
      await api.post('/musicat/connect', { musicat_handle: mcHandle });
      await refresh();
      await loadConnections();
      navigate('/dashboard');
    } catch (err) {
      setMcError(err.response?.data?.message || 'Could not connect that account.');
    } finally {
      setMcBusy(null);
    }
  };

  const disconnectMusicat = async () => {
    setMcError(null);
    setMcBusy('disconnect');
    try {
      await api.delete('/musicat/connection');
      await refresh();
      setMcHandle('');
      await loadConnections();
    } catch (err) {
      setMcError(err.response?.data?.message || 'Could not disconnect that account.');
    } finally {
      setMcBusy(null);
    }
  };

  const hasAnyConnection = (sfConnections && sfConnections.length > 0) || mcConnection;

  return (
    <div className="min-h-[80vh] flex items-center justify-center px-4 sm:px-6 py-12">
      <motion.div
        initial={{ opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
        className="w-full max-w-3xl"
      >
        <div className="flex justify-center mb-5">
          <Waveform bars={5} />
        </div>
        <h1 className="font-display text-2xl font-semibold text-center text-white mb-2">
          Connect your listening
        </h1>
        <p className="text-center text-mist text-sm mb-8 max-w-lg mx-auto">
          Link Spotify via Stats.fm — as many accounts as you like — and Apple Music via
          Musicat. Every connected account keeps fully separate stats.
        </p>

        <div className="grid sm:grid-cols-2 gap-6 items-start">
          <SpotifyPanel
            connections={sfConnections ?? []}
            isLoading={sfConnections === undefined}
            maxConnections={sfMax}
            onAdded={handleSpotifyAdded}
            onRemoved={handleSpotifyRemoved}
          />

          <AppleMusicPanel
            connection={mcConnection}
            isLoading={mcConnection === undefined}
            handle={mcHandle}
            setHandle={setMcHandle}
            error={mcError}
            busy={mcBusy}
            onConnect={connectMusicat}
            onDisconnect={disconnectMusicat}
          />
        </div>

        {hasAnyConnection && (
          <div className="text-center mt-8">
            <button
              onClick={() => navigate('/dashboard')}
              className="text-sm text-mist hover:text-white transition-colors"
            >
              Back to My Stats
            </button>
          </div>
        )}
      </motion.div>
    </div>
  );
}
