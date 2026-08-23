import { motion } from 'framer-motion';
import Waveform from '../components/Waveform';
import LevelUpQueue from '../components/LevelUpQueue';
import useStreamerLevelUps from '../hooks/useStreamerLevelUps';
import { themeForArtistName } from '../lib/artistThemes';

function AchievementBadge({ achievement }) {
  const theme = themeForArtistName(achievement.member_name ?? achievement.artist_name ?? '');
  const label = achievement.song_title
    ? achievement.song_title
    : achievement.member_name ?? achievement.artist_name;
  const sub = achievement.song_title
    ? `by ${achievement.member_name ?? achievement.artist_name}`
    : achievement.type === 'artist'
      ? 'Group total'
      : 'Solo total';

  return (
    <motion.div
      initial={{ opacity: 0, scale: 0.92 }}
      animate={{ opacity: 1, scale: 1 }}
      transition={{ duration: 0.25 }}
      className="rounded-2xl overflow-hidden p-4 flex flex-col items-center text-center"
      style={{
        background: `linear-gradient(160deg, ${theme.gradientFrom} 0%, ${theme.gradientTo} 100%)`,
        border: `1px solid ${theme.border}`,
      }}
    >
      <div
        className="w-12 h-12 rounded-xl overflow-hidden flex items-center justify-center mb-3"
        style={{ background: theme.accentSoft, boxShadow: `0 0 0 2px ${theme.border}` }}
      >
        {achievement.image_url ? (
          <img src={achievement.image_url} alt="" className="w-full h-full object-cover" />
        ) : (
          <svg viewBox="0 0 24 24" className="w-5 h-5" style={{ color: theme.accent }} fill="none" stroke="currentColor" strokeWidth="1.6">
            <path strokeLinecap="round" strokeLinejoin="round" d="M9 18V5l12-2v13M9 18a3 3 0 11-6 0 3 3 0 016 0zm12-2a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
        )}
      </div>

      <div
        className="w-14 h-14 rounded-full flex flex-col items-center justify-center mb-2"
        style={{
          background: `radial-gradient(circle at 30% 25%, ${theme.accentSoft}, transparent 70%), rgba(0,0,0,0.35)`,
          border: `2px solid ${theme.accent}`,
        }}
      >
        <span className="text-[8px] uppercase tracking-wider" style={{ color: theme.subtext }}>
          Lv
        </span>
        <span className="font-display font-bold leading-none text-lg" style={{ color: theme.text }}>
          {achievement.level}
        </span>
      </div>

      <p className="text-xs font-semibold truncate w-full" style={{ color: theme.text }}>
        {label}
      </p>
      <p className="text-[10px] mb-1" style={{ color: theme.subtext }}>
        {sub}
      </p>
      <span
        className="text-[9px] uppercase tracking-wider font-semibold px-2 py-0.5 rounded-full"
        style={{ color: theme.accent, background: theme.accentSoft }}
      >
        {achievement.tier}
      </span>
    </motion.div>
  );
}

/**
 * Every level this user has ever unlocked, across every counter
 * (BLACKPINK overall, each member's overall, and every song) — this is
 * just a read-only view over the streamer_achievements table via GET
 * /me/streamer-levels; nothing here is computed client-side.
 *
 * Also renders LevelUpQueue so that if this happens to be the first page
 * the user lands on after a sync (rather than the Dashboard), any
 * newly-crossed level still gets its celebration popup instead of being
 * silently marked "seen" with nothing shown.
 */
export default function Achievements() {
  const { achievements, loading } = useStreamerLevelUps();

  return (
    <div className="max-w-6xl mx-auto px-4 sm:px-6 pb-24 pt-8 sm:pt-12">
      <LevelUpQueue />

      <div className="mb-8">
        <p className="text-xs uppercase tracking-[0.2em] text-violet-bright font-semibold mb-1">
          Your Progress
        </p>
        <h1 className="font-display text-2xl sm:text-3xl font-semibold text-white">Achievements</h1>
        <p className="text-sm text-mist mt-2 max-w-xl">
          Every level you've unlocked, permanently — stored on your account, not just this browser, so it
          follows you to any device you log into.
        </p>
      </div>

      {loading && (
        <div className="flex justify-center py-24">
          <Waveform bars={5} className="scale-150" />
        </div>
      )}

      {!loading && achievements.length === 0 && (
        <p className="text-sm text-mist text-center py-12">
          No achievements unlocked yet — connect an account and sync your plays to start leveling up.
        </p>
      )}

      {!loading && achievements.length > 0 && (
        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3 sm:gap-4">
          {achievements.map((a) => (
            <AchievementBadge key={`${a.key}:${a.level}`} achievement={a} />
          ))}
        </div>
      )}
    </div>
  );
}
