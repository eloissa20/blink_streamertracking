import { motion, useReducedMotion } from 'framer-motion';
import Waveform from '../components/Waveform';
import AnimatedCounter from '../components/AnimatedCounter';
import LevelUpQueue from '../components/LevelUpQueue';
import useStreamerLevelUps from '../hooks/useStreamerLevelUps';
import { themeForArtistName } from '../lib/artistThemes';

// Circumference for the progress ring traced around the level circle.
// Unlocked badges are, by definition, "complete" (their level is already
// earned) — the ring animates 0 -> 100% once on mount as a satisfying
// unlock flourish rather than representing progress toward a next level
// (the API doesn't return in-progress/locked counters, only earned ones).
const RING_RADIUS = 30;
const RING_CIRCUMFERENCE = 2 * Math.PI * RING_RADIUS;

function AchievementBadge({ achievement, index, isNew }) {
  const theme = themeForArtistName(achievement.member_name ?? achievement.artist_name ?? '');
  const prefersReducedMotion = useReducedMotion();
  const label = achievement.song_title
    ? achievement.song_title
    : achievement.member_name ?? achievement.artist_name;
  const sub = achievement.song_title
    ? `by ${achievement.member_name ?? achievement.artist_name}`
    : achievement.type === 'artist'
      ? 'Group total'
      : 'Solo total';

  const entranceDelay = prefersReducedMotion ? 0 : Math.min(index * 0.04, 0.6);

  return (
    <motion.div
      initial={{ opacity: 0, y: 14, scale: 0.92 }}
      animate={
        isNew && !prefersReducedMotion
          ? { opacity: 1, y: 0, scale: [0.92, 1.08, 1] }
          : { opacity: 1, y: 0, scale: 1 }
      }
      transition={{ duration: isNew ? 0.55 : 0.35, delay: entranceDelay, ease: [0.16, 1, 0.3, 1] }}
      className="relative rounded-2xl overflow-hidden p-4 flex flex-col items-center text-center"
      style={{
        background: `linear-gradient(160deg, ${theme.gradientFrom} 0%, ${theme.gradientTo} 100%)`,
        border: `1px solid ${theme.border}`,
      }}
    >
      {/* Newly-unlocked badges glow softly after their one-time pulse above. */}
      {isNew && !prefersReducedMotion && (
        <motion.div
          className="absolute inset-0 pointer-events-none rounded-2xl"
          initial={{ boxShadow: `0 0 0px 0px ${theme.glow}` }}
          animate={{ boxShadow: [`0 0 0px 0px ${theme.glow}`, `0 0 22px 2px ${theme.glow}`, `0 0 12px 1px ${theme.glow}`] }}
          transition={{ duration: 1.8, delay: 0.5, ease: 'easeOut' }}
        />
      )}

      {/* Subtle sparkle for recently-completed items. */}
      {isNew && !prefersReducedMotion && (
        <motion.span
          aria-hidden="true"
          className="absolute top-3 right-3 w-1.5 h-1.5 rounded-full"
          style={{ background: theme.accent, boxShadow: `0 0 8px 2px ${theme.glow}` }}
          animate={{ opacity: [0, 1, 0], scale: [0.6, 1.3, 0.6], y: [0, -4, 0] }}
          transition={{ duration: 1.6, repeat: Infinity, repeatDelay: 0.4, ease: 'easeInOut' }}
        />
      )}

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

      <div className="relative w-14 h-14 mb-2">
        <svg viewBox="0 0 68 68" className="absolute inset-0 w-full h-full -rotate-90">
          <circle cx="34" cy="34" r={RING_RADIUS} fill="none" stroke={theme.accentSoft} strokeWidth="3" />
          <motion.circle
            cx="34"
            cy="34"
            r={RING_RADIUS}
            fill="none"
            stroke={theme.accent}
            strokeWidth="3"
            strokeLinecap="round"
            strokeDasharray={RING_CIRCUMFERENCE}
            initial={{ strokeDashoffset: RING_CIRCUMFERENCE }}
            animate={{ strokeDashoffset: 0 }}
            transition={{ duration: prefersReducedMotion ? 0 : 1, delay: entranceDelay + 0.1, ease: 'easeOut' }}
            style={{ filter: `drop-shadow(0 0 4px ${theme.glow})` }}
          />
        </svg>
        <div
          className="absolute inset-[3px] rounded-full flex flex-col items-center justify-center"
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
  const { achievements, newlyUnlockedKeys, loading } = useStreamerLevelUps();

  return (
    <div className="max-w-6xl mx-auto px-4 sm:px-6 pb-24 pt-8 sm:pt-12">
      <LevelUpQueue />

      <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-xs uppercase tracking-[0.2em] text-violet-bright font-semibold mb-1">
            Your Progress
          </p>
          <h1 className="font-display text-2xl sm:text-3xl font-semibold text-white">Achievements</h1>
          <p className="text-sm text-mist mt-2 max-w-xl">
            Every level you've unlocked, permanently — stored on your account, not just this browser, so it
            follows you to any device you log into.
          </p>
        </div>

        {!loading && achievements.length > 0 && (
          <div className="text-right">
            <p className="font-mono tabular text-2xl sm:text-3xl font-semibold text-white">
              <AnimatedCounter value={achievements.length} />
            </p>
            <p className="text-[10px] uppercase tracking-[0.15em] text-mist">Unlocked</p>
          </div>
        )}
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
          {achievements.map((a, i) => {
            const key = `${a.key}:${a.level}`;
            return (
              <AchievementBadge key={key} achievement={a} index={i} isNew={newlyUnlockedKeys.has(key)} />
            );
          })}
        </div>
      )}
    </div>
  );
}
