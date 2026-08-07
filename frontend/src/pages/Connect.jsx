import { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import api from '../api/client';
import { useAuth } from '../lib/AuthContext';
import Waveform from '../components/Waveform';
import { SpotifyIcon, AppleMusicIcon } from '../components/BrandIcons';

const SOURCE_LABEL = {
  spotify: 'Spotify',
  apple_music: 'Apple Music',
};

export default function Connect() {
  const { refresh } = useAuth();
  const navigate = useNavigate();
  const [handle, setHandle] = useState('');
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(null); // 'spotify' | 'apple_music' | 'disconnect' | null
  const [connection, setConnection] = useState(undefined); // undefined = loading, null = none

  const loadConnection = async () => {
    const { data } = await api.get('/statsfm/connection');
    setConnection(data.connected ? data.connection : null);
  };

  useEffect(() => {
    loadConnection();
  }, []);

  const submit = async (e, source) => {
    e.preventDefault();
    if (!handle) return;
    setError(null);
    setBusy(source);
    try {
      await api.post('/statsfm/connect', { statsfm_handle: handle, source });
      await refresh();
      navigate('/dashboard');
    } catch (err) {
      setError(err.response?.data?.message || 'Could not connect that account.');
    } finally {
      setBusy(null);
    }
  };

  const disconnect = async () => {
    setError(null);
    setBusy('disconnect');
    try {
      await api.delete('/statsfm/connection');
      await refresh();
      setHandle('');
      await loadConnection();
    } catch (err) {
      setError(err.response?.data?.message || 'Could not disconnect that account.');
    } finally {
      setBusy(null);
    }
  };

  const connectedSource = connection?.connected_source;
  const isLoading = connection === undefined;

  return (
    <div className="min-h-[80vh] flex items-center justify-center px-4 sm:px-6">
      <motion.div
        initial={{ opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
        className="w-full max-w-md glass-card rounded-3xl p-6 sm:p-8"
      >
        <div className="flex justify-center mb-5">
          <Waveform bars={5} />
        </div>
        <h1 className="font-display text-2xl font-semibold text-center text-white mb-1">
          Connect Stats.fm
        </h1>

        {isLoading && (
          <div className="flex justify-center py-10">
            <Waveform bars={5} />
          </div>
        )}

        {!isLoading && connection && (
          <>
            <p className="text-center text-mist text-sm mb-8">
              You're tracking <span className="text-white font-medium">{SOURCE_LABEL[connectedSource] || 'a service'}</span> via
              Stats.fm ({connection.statsfm_username}). To switch services, disconnect first.
            </p>

            {error && <p className="text-sm text-apple text-center mb-4">{error}</p>}

            <div className="flex flex-col gap-4">
              <div className="flex items-center gap-3 rounded-xl border border-white/10 px-4 py-3">
                {connectedSource === 'apple_music' ? (
                  <AppleMusicIcon className="w-5 h-5 text-apple" />
                ) : (
                  <SpotifyIcon className="w-5 h-5 text-spotify" />
                )}
                <div className="min-w-0">
                  <p className="text-sm font-medium text-white truncate">
                    {SOURCE_LABEL[connectedSource] || 'Connected'}
                  </p>
                  <p className="text-xs text-mist truncate">{connection.statsfm_username}</p>
                </div>
              </div>

              <button
                onClick={disconnect}
                disabled={!!busy}
                className="rounded-xl py-3 font-medium border border-white/10 hover:border-apple/50 hover:text-apple transition-colors disabled:opacity-50"
              >
                {busy === 'disconnect' ? 'Disconnecting…' : `Disconnect ${SOURCE_LABEL[connectedSource] || ''}`}
              </button>

              <button
                onClick={() => navigate('/dashboard')}
                className="text-sm text-mist hover:text-white transition-colors"
              >
                Back to My Stats
              </button>
            </div>
          </>
        )}

        {!isLoading && !connection && (
          <>
            <p className="text-center text-mist text-sm mb-8">
              Pick one service to track. You can switch later, but you'll need to disconnect
              first — only one service can be connected at a time.
            </p>

            <form className="flex flex-col gap-4">
              <input
                type="text"
                required
                placeholder="Your Stats.fm username"
                value={handle}
                onChange={(e) => setHandle(e.target.value)}
                className="bg-ink-surface border border-ink-border rounded-xl px-4 py-3 text-sm outline-none focus:border-violet transition-colors"
              />
              {error && <p className="text-sm text-apple">{error}</p>}

              <button
                type="submit"
                onClick={(e) => submit(e, 'spotify')}
                disabled={!!busy}
                style={{ backgroundColor: '#1DB954' }}
                className="flex items-center justify-center gap-2 rounded-xl py-3 font-medium text-black hover:opacity-90 transition-opacity disabled:opacity-50"
              >
                <SpotifyIcon className="w-5 h-5" />
                {busy === 'spotify' ? 'Connecting…' : 'Connect with Spotify via Stats.fm'}
              </button>

              <button
                type="submit"
                onClick={(e) => submit(e, 'apple_music')}
                disabled={!!busy}
                style={{ backgroundImage: 'linear-gradient(120deg, #FA2D48 0%, #FB5C74 100%)' }}
                className="flex items-center justify-center gap-2 rounded-xl py-3 font-medium text-white hover:opacity-90 transition-opacity disabled:opacity-50"
              >
                <AppleMusicIcon className="w-5 h-5" />
                {busy === 'apple_music' ? 'Connecting…' : 'Connect with Apple Music via Stats.fm'}
              </button>
            </form>
          </>
        )}

        <p className="text-center text-xs text-mist mt-6">
          Don't have Stats.fm yet? Install it, link Spotify or Apple Music, make your profile
          public, then come back here with your username.
        </p>
      </motion.div>
    </div>
  );
}
