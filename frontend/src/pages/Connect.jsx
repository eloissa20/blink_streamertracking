import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import api from '../api/client';
import { useAuth } from '../lib/AuthContext';
import Waveform from '../components/Waveform';
import { SpotifyIcon, AppleMusicIcon } from '../components/BrandIcons';

function ServicePanel({
  icon,
  title,
  subtitle,
  placeholder,
  helpText,
  connection,
  isLoading,
  handle,
  setHandle,
  error,
  busy,
  onConnect,
  onDisconnect,
  accentStyle,
}) {
  return (
    <div className="glass-card rounded-3xl p-6 sm:p-8">
      <div className="flex items-center gap-3 mb-1">
        {icon}
        <h2 className="font-display text-xl font-semibold text-white">{title}</h2>
      </div>
      <p className="text-sm text-mist mb-6">{subtitle}</p>

      {isLoading && (
        <div className="flex justify-center py-8">
          <Waveform bars={5} />
        </div>
      )}

      {!isLoading && connection && (
        <>
          <div className="flex items-center gap-3 rounded-xl border border-white/10 px-4 py-3 mb-4">
            {icon}
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
            placeholder={placeholder}
            value={handle}
            onChange={(e) => setHandle(e.target.value)}
            className="bg-ink-surface border border-ink-border rounded-xl px-4 py-3 text-sm outline-none focus:border-violet transition-colors"
          />
          {error && <p className="text-sm text-apple">{error}</p>}
          <button
            type="submit"
            disabled={!!busy}
            style={accentStyle}
            className="flex items-center justify-center gap-2 rounded-xl py-3 font-medium hover:opacity-90 transition-opacity disabled:opacity-50"
          >
            {icon}
            {busy === 'connect' ? 'Connecting…' : 'Connect'}
          </button>
        </form>
      )}

      <p className="text-xs text-mist mt-5">{helpText}</p>
    </div>
  );
}

export default function Connect() {
  const { refresh } = useAuth();
  const navigate = useNavigate();

  // --- Stats.fm (Spotify) --------------------------------------------
  const [sfHandle, setSfHandle] = useState('');
  const [sfError, setSfError] = useState(null);
  const [sfBusy, setSfBusy] = useState(null); // 'connect' | 'disconnect' | null
  const [sfConnection, setSfConnection] = useState(undefined);

  // --- Musicat (Apple Music) ------------------------------------------
  const [mcHandle, setMcHandle] = useState('');
  const [mcError, setMcError] = useState(null);
  const [mcBusy, setMcBusy] = useState(null);
  const [mcConnection, setMcConnection] = useState(undefined);

  const loadConnections = async () => {
    const [sf, mc] = await Promise.all([
      api.get('/statsfm/connection'),
      api.get('/musicat/connection'),
    ]);
    setSfConnection(
      sf.data.connected ? { ...sf.data.connection, username: sf.data.connection.statsfm_username } : null
    );
    setMcConnection(
      mc.data.connected ? { ...mc.data.connection, username: mc.data.connection.musicat_username } : null
    );
  };

  useEffect(() => {
    loadConnections();
  }, []);

  const connectStatsFm = async (e) => {
    e.preventDefault();
    if (!sfHandle) return;
    setSfError(null);
    setSfBusy('connect');
    try {
      await api.post('/statsfm/connect', { statsfm_handle: sfHandle, source: 'spotify' });
      await refresh();
      await loadConnections();
      navigate('/dashboard');
    } catch (err) {
      setSfError(err.response?.data?.message || 'Could not connect that account.');
    } finally {
      setSfBusy(null);
    }
  };

  const disconnectStatsFm = async () => {
    setSfError(null);
    setSfBusy('disconnect');
    try {
      await api.delete('/statsfm/connection');
      await refresh();
      setSfHandle('');
      await loadConnections();
    } catch (err) {
      setSfError(err.response?.data?.message || 'Could not disconnect that account.');
    } finally {
      setSfBusy(null);
    }
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
          Link one or both — Spotify via Stats.fm, Apple Music via Musicat. Each is independent,
          so you can connect either service on its own or both at once.
        </p>

        <div className="grid sm:grid-cols-2 gap-6">
          <ServicePanel
            icon={<SpotifyIcon className="w-5 h-5 text-spotify" />}
            title="Spotify"
            subtitle="Tracked via Stats.fm"
            placeholder="Your Stats.fm username"
            helpText="Don't have Stats.fm yet? Install it, link Spotify, make your profile public, then come back here with your username."
            connection={sfConnection}
            isLoading={sfConnection === undefined}
            handle={sfHandle}
            setHandle={setSfHandle}
            error={sfError}
            busy={sfBusy}
            onConnect={connectStatsFm}
            onDisconnect={disconnectStatsFm}
            accentStyle={{ backgroundColor: '#1DB954', color: '#000' }}
          />

          <ServicePanel
            icon={<AppleMusicIcon className="w-5 h-5 text-apple" />}
            title="Apple Music"
            subtitle="Tracked via Musicat"
            placeholder="Your Musicat username"
            helpText="Don't have Musicat yet? Install it from musicat.fm, link Apple Music, make your profile public, then come back here with your username (the part after musicat.fm/)."
            connection={mcConnection}
            isLoading={mcConnection === undefined}
            handle={mcHandle}
            setHandle={setMcHandle}
            error={mcError}
            busy={mcBusy}
            onConnect={connectMusicat}
            onDisconnect={disconnectMusicat}
            accentStyle={{
              backgroundImage: 'linear-gradient(120deg, #FA2D48 0%, #FB5C74 100%)',
              color: '#fff',
            }}
          />
        </div>

        {(sfConnection || mcConnection) && (
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
