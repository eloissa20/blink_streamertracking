import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import api from '../api/client';
import Waveform from '../components/Waveform';
import { THEMES } from '../lib/artistThemes';

function MissionCard({ mission }) {
  const theme = THEMES[mission.theme_key] ?? THEMES.blackpink;
  const percent = Math.round(mission.progress * 100);

  return (
    <motion.div
      initial={{ opacity: 0, y: 12 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.35 }}
      className="rounded-3xl overflow-hidden p-5 sm:p-6"
      style={{
        background: `linear-gradient(160deg, ${theme.gradientFrom} 0%, ${theme.gradientTo} 100%)`,
        border: `1px solid ${theme.border}`,
        boxShadow: mission.is_complete ? `0 0 40px -10px ${theme.glow}` : 'none',
      }}
    >
      <div className="flex items-start justify-between gap-3 mb-3">
        <div>
          <span
            className="inline-block text-[10px] uppercase tracking-[0.2em] font-semibold px-2.5 py-1 rounded-full mb-2"
            style={{ color: theme.accent, background: theme.accentSoft }}
          >
            {mission.is_per_song ? 'Song Mission' : 'Artist Mission'}
          </span>
          <h3 className="font-display text-lg sm:text-xl font-bold" style={{ color: theme.text }}>
            {mission.title}
          </h3>
        </div>
        {mission.is_complete && (
          <span className="text-2xl" title="Mission complete!">
            🏆
          </span>
        )}
      </div>

      {mission.description && (
        <p className="text-sm mb-4" style={{ color: theme.subtext }}>
          {mission.description}
        </p>
      )}

      <div className="flex items-center justify-between text-xs mb-1.5" style={{ color: theme.subtext }}>
        <span>
          {Number(mission.current_streams).toLocaleString()} / {Number(mission.target_streams).toLocaleString()} streams
        </span>
        <span>{percent}%</span>
      </div>

      <div className="w-full h-2.5 rounded-full overflow-hidden" style={{ background: 'rgba(255,255,255,0.08)' }}>
        <motion.div
          className="h-full rounded-full"
          style={{ background: theme.accent, boxShadow: `0 0 10px ${theme.glow}` }}
          initial={{ width: 0 }}
          animate={{ width: `${percent}%` }}
          transition={{ duration: 0.8, ease: 'easeOut' }}
        />
      </div>

      <p className="text-[11px] mt-3" style={{ color: theme.subtext }}>
        {mission.contributors} streamer{mission.contributors === 1 ? '' : 's'} contributing
        {mission.ends_at ? ` · ends ${new Date(mission.ends_at).toLocaleDateString()}` : ''}
      </p>
    </motion.div>
  );
}

export default function Missions() {
  const [missions, setMissions] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    let cancelled = false;

    api
      .get('/missions')
      .then(({ data }) => {
        if (!cancelled) setMissions(data.missions ?? []);
      })
      .catch(() => {
        if (!cancelled) setError("Couldn't load missions right now — try again in a bit.");
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, []);

  return (
    <div className="max-w-6xl mx-auto px-4 sm:px-6 pb-24 pt-8 sm:pt-12">
      <div className="mb-8">
        <p className="text-xs uppercase tracking-[0.2em] text-violet-bright font-semibold mb-1">
          Community
        </p>
        <h1 className="font-display text-2xl sm:text-3xl font-semibold text-white">Streaming Missions</h1>
        <p className="text-sm text-mist mt-2 max-w-xl">
          Shared goals every tracked streamer counts toward — your plays add to the same progress bar
          everyone else sees, no matter which account you're signed in with.
        </p>
      </div>

      {loading && (
        <div className="flex justify-center py-24">
          <Waveform bars={5} className="scale-150" />
        </div>
      )}

      {!loading && error && <p className="text-sm text-red-400 text-center py-12">{error}</p>}

      {!loading && !error && missions.length === 0 && (
        <p className="text-sm text-mist text-center py-12">No active missions right now — check back soon.</p>
      )}

      {!loading && !error && missions.length > 0 && (
        <div className="grid sm:grid-cols-2 gap-4 sm:gap-6">
          {missions.map((m) => (
            <MissionCard key={m.id} mission={m} />
          ))}
        </div>
      )}
    </div>
  );
}
