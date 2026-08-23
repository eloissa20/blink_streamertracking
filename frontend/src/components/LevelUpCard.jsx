import { useEffect, useMemo, useState } from 'react';
import { AnimatePresence, motion } from 'framer-motion';

const AUTO_CLOSE_MS = 7000;

// Deterministic-but-varied particle layout so the ambient light dots
// don't re-shuffle on every re-render.
function useParticles(count, colors) {
  return useMemo(
    () =>
      Array.from({ length: count }, (_, i) => ({
        id: i,
        left: `${(i * 37) % 100}%`,
        delay: (i % 10) * 0.12,
        duration: 2.2 + ((i * 13) % 10) / 10,
        size: 4 + ((i * 7) % 6),
        color: colors[i % colors.length],
        drift: ((i % 5) - 2) * 18,
      })),
    [count, colors]
  );
}

// A one-time confetti burst — small rectangular pieces that fly out from
// just above center, tumble, and fall, rather than the ambient particles
// above (which are soft looping light dots, not confetti). Fires once
// when the card mounts; doesn't repeat like the ambient particles do.
function useConfettiBurst(count, colors) {
  return useMemo(
    () =>
      Array.from({ length: count }, (_, i) => {
        const angle = (i / count) * Math.PI * 2 + (i % 3) * 0.4;
        const spread = 90 + ((i * 53) % 70); // how far it flies horizontally
        return {
          id: i,
          color: colors[i % colors.length],
          startX: 50 + (((i * 17) % 20) - 10), // cluster near center, not a single point
          width: 5 + (i % 3) * 2,
          height: 9 + (i % 3) * 3,
          x: Math.cos(angle) * spread,
          fallY: 260 + ((i * 29) % 160),
          rotate: ((i * 47) % 720) - 360,
          delay: (i % 12) * 0.02,
          duration: 1.6 + ((i * 11) % 8) / 10,
        };
      }),
    [count, colors]
  );
}

/**
 * @param {'artist' | 'solo'} type - which of the two card designs to render
 * @param {object} theme - one of the THEMES entries from lib/artistThemes
 * @param {number} level
 * @param {string} tier - 'Junior Streamer' | 'Real Streamer' | 'Pro Streamer'
 * @param {number} totalStreams
 * @param {number} progress - 0-1 fraction toward the next level
 * @param {string} artistName - shown on the group card
 * @param {string} [memberName] - shown on the solo card
 * @param {string} [songTitle] - shown on the solo card
 * @param {string} [imageUrl] - optional artwork/portrait already present in
 *   the user's own synced Spotify/Apple Music data (album art etc.) — this
 *   component never fetches or generates images of real people itself.
 * @param {() => void} onClose
 */
export default function LevelUpCard({
  type = 'artist',
  theme,
  level,
  tier,
  totalStreams,
  progress = 0,
  artistName = 'BLACKPINK',
  memberName,
  songTitle,
  imageUrl,
  onClose,
}) {
  const [closing, setClosing] = useState(false);
  const particles = useParticles(type === 'artist' ? 26 : 20, theme.particleColors);
  const confetti = useConfettiBurst(type === 'artist' ? 36 : 26, theme.particleColors);

  const handleClose = () => {
    setClosing(true);
    if (navigator.vibrate) navigator.vibrate(12);
    setTimeout(onClose, 200);
  };

  useEffect(() => {
    if (navigator.vibrate) navigator.vibrate([10, 40, 10]);
    const t = setTimeout(handleClose, AUTO_CLOSE_MS);
    return () => clearTimeout(t);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const heading = type === 'artist' ? 'Congratulations!' : songTitle ? 'Song Milestone Unlocked!' : 'Solo Level Up!';
  const subtitle =
    type === 'artist'
      ? `You've reached Level ${level} as a ${tier}`
      : `Level ${level} reached on ${songTitle ?? 'this track'}`;
  // Falls back to artistName so a BLACKPINK group song (memberName is
  // undefined — it's not any one member's solo work) still reads as
  // "<Song> by BLACKPINK now has..." instead of dropping the attribution
  // entirely.
  const extraLine =
    type === 'artist'
      ? `Total streams for ${artistName}: ${Number(totalStreams).toLocaleString()}`
      : `${songTitle ?? 'This track'} by ${memberName ?? artistName} now has ${Number(
          totalStreams
        ).toLocaleString()} streams`;

  return (
    <AnimatePresence>
      {!closing && (
        <motion.div
          className="fixed inset-0 z-[100] flex items-center justify-center p-4"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          exit={{ opacity: 0 }}
          transition={{ duration: 0.2 }}
          role="dialog"
          aria-modal="true"
          aria-label={`${heading} ${subtitle}`}
        >
          {/* overlay */}
          <div className="absolute inset-0 bg-black/70 backdrop-blur-sm" onClick={handleClose} />

          {/* card */}
          <motion.div
            initial={{ opacity: 0, scale: 0.85, y: 20 }}
            animate={{ opacity: 1, scale: 1, y: 0 }}
            exit={{ opacity: 0, scale: 0.9, y: 10 }}
            transition={{ type: 'spring', stiffness: 260, damping: 22 }}
            className="relative w-full max-w-sm sm:max-w-md rounded-[1.75rem] overflow-hidden"
            style={{
              background: `linear-gradient(160deg, ${theme.gradientFrom} 0%, ${theme.gradientTo} 100%)`,
              border: `1px solid ${theme.border}`,
              boxShadow: `0 0 0 1px rgba(255,255,255,0.04), 0 25px 80px -20px ${theme.glow}, 0 0 60px -10px ${theme.glow}`,
            }}
          >
            {/* one-time confetti burst, fired on mount */}
            <div className="pointer-events-none absolute inset-0 overflow-hidden">
              {confetti.map((c) => (
                <motion.span
                  key={c.id}
                  className="absolute rounded-[1px]"
                  style={{
                    left: `${c.startX}%`,
                    top: '38%',
                    width: c.width,
                    height: c.height,
                    background: c.color,
                  }}
                  initial={{ x: 0, y: 0, opacity: 0, rotate: 0 }}
                  animate={{
                    x: [0, c.x * 0.6, c.x],
                    y: [0, -30, c.fallY],
                    opacity: [0, 1, 1, 0],
                    rotate: [0, c.rotate * 0.5, c.rotate],
                  }}
                  transition={{
                    duration: c.duration,
                    delay: c.delay,
                    times: [0, 0.15, 1],
                    ease: ['easeOut', 'easeIn'],
                  }}
                />
              ))}
            </div>

            {/* ambient soft light particles — loop continuously for as
                long as the card is open, distinct from the one-time
                confetti burst above */}
            <div className="pointer-events-none absolute inset-0 overflow-hidden">
              {particles.map((p) => (
                <motion.span
                  key={p.id}
                  className="absolute rounded-full"
                  style={{
                    left: p.left,
                    top: '-10%',
                    width: p.size,
                    height: p.size,
                    background: p.color,
                    opacity: 0.85,
                  }}
                  animate={{
                    y: ['0%', '520%'],
                    x: [0, p.drift],
                    opacity: [0, 0.9, 0],
                    rotate: [0, 180],
                  }}
                  transition={{
                    duration: p.duration,
                    delay: p.delay,
                    repeat: Infinity,
                    ease: 'linear',
                  }}
                />
              ))}
            </div>

            <button
              onClick={handleClose}
              aria-label="Close"
              className="absolute top-3 right-3 w-8 h-8 rounded-full flex items-center justify-center text-white/70 hover:text-white bg-white/5 hover:bg-white/10 transition-colors z-10"
            >
              ✕
            </button>

            <div className="relative px-6 sm:px-8 pt-10 pb-7 flex flex-col items-center text-center">
              {/* artwork icon — the actual song/artist image already
                  present in the user's own synced Spotify/Apple Music
                  data (album art for a song counter, artist image for a
                  group/solo overall counter). This component only ever
                  displays media the app already has on file; it never
                  fetches or generates an image itself. A generic
                  fallback glyph (no photo, no initials) is shown only if
                  no image ever came through, so the layout never breaks. */}
              <div
                className="w-16 h-16 rounded-2xl overflow-hidden flex items-center justify-center mb-8"
                style={{
                  background: theme.accentSoft,
                  boxShadow: `0 0 0 2px ${theme.border}, 0 0 24px ${theme.glow}`,
                }}
              >
                {imageUrl ? (
                  <img src={imageUrl} alt="" className="w-full h-full object-cover" />
                ) : (
                  <svg
                    viewBox="0 0 24 24"
                    className="w-7 h-7"
                    style={{ color: theme.accent }}
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1.6"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      d="M9 18V5l12-2v13M9 18a3 3 0 11-6 0 3 3 0 016 0zm12-2a3 3 0 11-6 0 3 3 0 016 0z"
                    />
                  </svg>
                )}
              </div>

              {/* level badge — kept a full mb-8 below the icon above (on
                  top of its own glow blur) so the glow never visually
                  merges with the icon box regardless of theme */}
              <motion.div
                initial={{ scale: 0.6, opacity: 0 }}
                animate={{ scale: 1, opacity: 1 }}
                transition={{ delay: 0.15, type: 'spring', stiffness: 200, damping: 14 }}
                className="relative mb-4"
              >
                <div
                  className="absolute inset-2 rounded-full blur-xl animate-pulse"
                  style={{ background: theme.glow }}
                />
                <div
                  className="relative w-28 h-28 sm:w-32 sm:h-32 rounded-full flex flex-col items-center justify-center"
                  style={{
                    background: `radial-gradient(circle at 30% 25%, ${theme.accentSoft}, transparent 70%), rgba(0,0,0,0.35)`,
                    border: `2px solid ${theme.accent}`,
                  }}
                >
                  <span className="text-[10px] uppercase tracking-[0.2em]" style={{ color: theme.subtext }}>
                    Level
                  </span>
                  <span
                    className="font-display font-bold leading-none text-4xl sm:text-5xl"
                    style={{ color: theme.text, textShadow: `0 0 18px ${theme.glow}` }}
                  >
                    {level}
                  </span>
                </div>
              </motion.div>

              <span
                className="text-[10px] uppercase tracking-[0.25em] font-semibold mb-2 px-3 py-1 rounded-full"
                style={{ color: theme.accent, background: theme.accentSoft }}
              >
                {tier}
              </span>

              <h2 className="font-display text-xl sm:text-2xl font-bold mb-1" style={{ color: theme.text }}>
                {heading}
              </h2>
              <p className="text-sm mb-1" style={{ color: theme.subtext }}>
                {subtitle}
              </p>
              <p className="text-xs mb-5" style={{ color: theme.subtext }}>
                {extraLine}
              </p>

              {/* progress bar toward next level */}
              <div className="w-full h-2 rounded-full overflow-hidden" style={{ background: 'rgba(255,255,255,0.08)' }}>
                <motion.div
                  className="h-full rounded-full"
                  style={{ background: theme.accent, boxShadow: `0 0 10px ${theme.glow}` }}
                  initial={{ width: 0 }}
                  animate={{ width: `${Math.round(progress * 100)}%` }}
                  transition={{ delay: 0.3, duration: 0.8, ease: 'easeOut' }}
                />
              </div>
              <p className="text-[11px] mt-2" style={{ color: theme.subtext }}>
                {Math.round(progress * 100)}% to Level {level + 1}
              </p>
            </div>
          </motion.div>
        </motion.div>
      )}
    </AnimatePresence>
  );
}
