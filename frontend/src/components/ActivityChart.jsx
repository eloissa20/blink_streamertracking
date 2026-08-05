import { motion } from 'framer-motion';

const WIDTH = 720;
const HEIGHT = 260;
const PAD_LEFT = 32;
const PAD_RIGHT = 12;
const PAD_TOP = 16;
const PAD_BOTTOM = 32;
const Y_MAX = 12;

function buildPath(days, key, xFor, yFor) {
  return days
    .map((d, i) => `${i === 0 ? 'M' : 'L'} ${xFor(i)} ${yFor(d[key])}`)
    .join(' ');
}

function shortDateLabel(iso) {
  return new Date(iso + 'T00:00:00').toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
  });
}

export default function ActivityChart({ days }) {
  const chartW = WIDTH - PAD_LEFT - PAD_RIGHT;
  const chartH = HEIGHT - PAD_TOP - PAD_BOTTOM;

  const xFor = (i) =>
    PAD_LEFT + (days.length <= 1 ? 0 : (i / (days.length - 1)) * chartW);
  const yFor = (v) => PAD_TOP + chartH - (Math.min(v, Y_MAX) / Y_MAX) * chartH;

  const spotifyPath = buildPath(days, 'spotify', xFor, yFor);
  const appleMusicPath = buildPath(days, 'apple_music', xFor, yFor);

  const yTicks = [0, 3, 6, 9, 12];
  const xTickEvery = Math.max(1, Math.floor(days.length / 6));

  return (
    <div className="glass-card rounded-3xl p-6 md:p-8">
      <div className="mb-6">
        <p className="text-xs uppercase tracking-[0.2em] text-violet-bright font-semibold mb-1">
          Activity
        </p>
        <h2 className="font-display text-2xl font-semibold text-white">Last 30 days</h2>
      </div>

      <motion.svg
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ duration: 0.5 }}
        viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
        className="w-full h-auto"
      >
        {/* soft grid lines */}
        {yTicks.map((t) => (
          <line
            key={t}
            x1={PAD_LEFT}
            x2={WIDTH - PAD_RIGHT}
            y1={yFor(t)}
            y2={yFor(t)}
            stroke="rgba(255,255,255,0.06)"
            strokeWidth="1"
          />
        ))}

        {/* y-axis labels */}
        {yTicks.map((t) => (
          <text
            key={t}
            x={PAD_LEFT - 8}
            y={yFor(t) + 3}
            textAnchor="end"
            fontSize="10"
            fontFamily="monospace"
            fill="#9C9AB8"
          >
            {t}
          </text>
        ))}

        {/* x-axis labels */}
        {days.map((d, i) =>
          i % xTickEvery === 0 ? (
            <text
              key={d.date}
              x={xFor(i)}
              y={HEIGHT - PAD_BOTTOM + 18}
              textAnchor="middle"
              fontSize="10"
              fontFamily="monospace"
              fill="#9C9AB8"
            >
              {shortDateLabel(d.date)}
            </text>
          ) : null
        )}

        {/* apple music line (behind spotify) */}
        <motion.path
          initial={{ pathLength: 0 }}
          animate={{ pathLength: 1 }}
          transition={{ duration: 0.9, ease: 'easeOut' }}
          d={appleMusicPath}
          fill="none"
          stroke="#FA2D48"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
        />

        {/* spotify line */}
        <motion.path
          initial={{ pathLength: 0 }}
          animate={{ pathLength: 1 }}
          transition={{ duration: 0.9, ease: 'easeOut', delay: 0.1 }}
          d={spotifyPath}
          fill="none"
          stroke="#1DB954"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
      </motion.svg>

      <div className="flex items-center justify-center gap-6 mt-4">
        <span className="flex items-center gap-2 text-xs text-mist">
          <span className="w-2.5 h-2.5 rounded-full" style={{ background: '#1DB954' }} />
          Spotify
        </span>
        <span className="flex items-center gap-2 text-xs text-mist">
          <span className="w-2.5 h-2.5 rounded-full" style={{ background: '#FA2D48' }} />
          Apple Music
        </span>
      </div>
    </div>
  );
}
