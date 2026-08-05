import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { motion } from 'framer-motion';
import api from '../api/client';
import { useAuth } from '../lib/AuthContext';
import Waveform from '../components/Waveform';
import { SpotifyIcon, AppleMusicIcon } from '../components/BrandIcons';

export default function Connect() {
  const { refresh } = useAuth();
  const navigate = useNavigate();
  const [handle, setHandle] = useState('');
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(null); // 'spotify' | 'apple_music' | null

  const submit = async (e, source) => {
    e.preventDefault();
    if (!handle) return;
    setError(null);
    setBusy(source);
    try {
      await api.post('/statsfm/connect', { statsfm_handle: handle });
      await refresh();
      navigate('/dashboard');
    } catch (err) {
      setError(err.response?.data?.message || 'Could not connect that account.');
    } finally {
      setBusy(null);
    }
  };

  return (
    <div className="min-h-[80vh] flex items-center justify-center px-6">
      <motion.div
        initial={{ opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
        className="w-full max-w-md glass-card rounded-3xl p-8"
      >
        <div className="flex justify-center mb-5">
          <Waveform bars={5} />
        </div>
        <h1 className="font-display text-2xl font-semibold text-center text-white mb-1">
          Connect Stats.fm
        </h1>
        <p className="text-center text-mist text-sm mb-8">
          One account covers both Spotify and Apple Music — you can only link one, and it's just for you.
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

        <p className="text-center text-xs text-mist mt-6">
          Don't have Stats.fm yet? Install it, link Spotify or Apple Music, make your profile
          public, then come back here with your username.
        </p>
      </motion.div>
    </div>
  );
}
