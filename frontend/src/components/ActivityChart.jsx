import { motion } from 'framer-motion';
import { useState, useRef, useCallback } from 'react';

const WIDTH = 720;
const HEIGHT = 260;
const PAD_LEFT = 32;
const PAD_RIGHT = 12;
const PAD_TOP = 16;
const PAD_BOTTOM = 32;

/**
 * Pick a "nice" round y-axis max that comfortably fits the highest value
 * in the data, instead of a hardcoded cap. A hardcoded cap silently clips
 * any day above it to the same height as the cap itself (e.g. 36 streams
 * would render identically to 12), which erases real ups and downs.
 */
function niceYMax(days) {
  const rawMax = days.reduce(
    (max, d) => Math.max(max, d.spotify, d.apple_music),
    0
  );
  if (rawMax <= 12) return 12;
  const magnitude = Math.pow(10, Math.floor(Math.log10(rawMax)));
  const step = magnitude / 2 || 1;
  return Math.ceil((rawMax * 1.1) / step) * step;
}

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
  const [hoverIndex, setHoverIndex] = useState(null);
  const svgRef = useRef(null);

  const chartW = WIDTH - PAD_LEFT - PAD_RIGHT;
  const chartH = HEIGHT - PAD_TOP - PAD_BOTTOM;

  const yMax = niceYMax(days);

  const xFor = (i) =>
    PAD_LEFT + (days.length <= 1 ? 0 : (i / (days.length - 1)) * chartW);
  const yFor = (v) => PAD_TOP + chartH - (Math.min(v, yMax) / yMax) * chartH;

  const spotifyPath = buildPath(days, 'spotify', xFor, yFor);
  const appleMusicPath = buildPath(days, 'apple_music', xFor, yFor);

  const tickCount = 4;
  const yTicks = Array.from({ length: tickCount + 1 }, (_, i) =>
    Math.round((yMax / tickCount) * i)
  );
  const xTickEvery = Math.max(1, Math.floor(days.length / 6));

  const handleMove = useCallback(
    (e) => {
      if (!svgRef.current || days.length === 0) return;
      const rect = svgRef.current.getBoundingClientRect();
      // translate mouse position into the SVG's own coordinate space,
      // since the element is scaled by the viewBox to fill its container
      const scaleX = WIDTH / rect.width;
      const svgX = (e.clientX - rect.left) * scaleX;

      let closest = 0;
      let closestDist = Infinity;
      for (let i = 0; i < days.length; i++) {
        const dist = Math.abs(xFor(i) - svgX);
        if (dist < closestDist) {
          closestDist = dist;
          closest = i;
        }
      }
      setHoverIndex(closest);
    },
    [days, xFor]
  );

  const handleLeave = useCallback(() => setHoverIndex(null), []);

  const hovered = hoverIndex !== null ? days[hoverIndex] : null;

  // Keep the tooltip box on-screen near the right edge by flipping its
  // anchor once the hovered point gets close to PAD_RIGHT.
  const tooltipW = 150;
  const tooltipOnLeft = hovered ? xFor(hoverIndex) + 16 + tooltipW > WIDTH : false;
  const tooltipX = hovered
    ? tooltipOnLeft
      ? xFor(hoverIndex) - 16 - tooltipW
      : xFor(hoverIndex) + 16
    : 0;
  const tooltipY = hovered ? Math.max(PAD_TOP, yFor(Math.max(hovered.spotify, hovered.apple_music)) - 10) : 0;

  return (
    <div className="glass-card rounded-3xl p-4 sm:p-6 md:p-8">
      <div className="mb-6">
        <p className="text-xs uppercase tracking-[0.2em] text-violet-bright font-semibold mb-1">
          Activity
        </p>
        <h2 className="font-display text-xl sm:text-2xl font-semibold text-fg">Last 30 days</h2>
      </div>

      <motion.svg
        ref={svgRef}
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ duration: 0.5 }}
        viewBox={`0 0 ${WIDTH} ${HEIGHT}`}
        className="w-full h-auto cursor-crosshair"
        onMouseMove={handleMove}
        onMouseLeave={handleLeave}
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

        {/* hover guide + dots + tooltip */}
        {hovered && (
          <g>
            <line
              x1={xFor(hoverIndex)}
              x2={xFor(hoverIndex)}
              y1={PAD_TOP}
              y2={HEIGHT - PAD_BOTTOM}
              stroke="rgba(255,255,255,0.15)"
              strokeWidth="1"
            />
            <circle
              cx={xFor(hoverIndex)}
              cy={yFor(hovered.spotify)}
              r="4"
              fill="#1DB954"
              stroke="#0B0A17"
              strokeWidth="2"
            />
            <circle
              cx={xFor(hoverIndex)}
              cy={yFor(hovered.apple_music)}
              r="4"
              fill="#FA2D48"
              stroke="#0B0A17"
              strokeWidth="2"
            />

            <foreignObject x={tooltipX} y={tooltipY} width={tooltipW} height="72">
              <div className="rounded-lg border border-fg/10 bg-[#12101f] px-3 py-2 shadow-lg text-xs">
                <p className="text-mist font-mono mb-1.5">{shortDateLabel(hovered.date)}</p>
                <div className="flex items-center justify-between gap-3 mb-1">
                  <span className="flex items-center gap-1.5 text-fg">
                    <span className="w-2 h-2 rounded-full" style={{ background: '#1DB954' }} />
                    Spotify
                  </span>
                  <span className="font-mono tabular text-fg">{hovered.spotify}</span>
                </div>
                <div className="flex items-center justify-between gap-3">
                  <span className="flex items-center gap-1.5 text-fg">
                    <span className="w-2 h-2 rounded-full" style={{ background: '#FA2D48' }} />
                    Apple Music
                  </span>
                  <span className="font-mono tabular text-fg">{hovered.apple_music}</span>
                </div>
              </div>
            </foreignObject>
          </g>
        )}
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
