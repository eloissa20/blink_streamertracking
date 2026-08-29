import { useState } from 'react';
import LevelUpCard from '../components/LevelUpCard';
import { THEMES } from '../lib/artistThemes';
import { TIERS } from '../lib/levels';

// Mirrors backend/frontend tier boundaries just for picking a realistic
// example level per tier in this preview — not used anywhere else.
const SAMPLE_LEVEL_BY_TIER = {
  [TIERS.JUNIOR]: 5,
  [TIERS.REAL]: 18,
  [TIERS.PRO]: 42,
};

const TIER_OPTIONS = ['Junior Streamer', 'Real Streamer', 'Pro Streamer'];

const SONGS_BY_MEMBER = {
  blackpink: 'Jump',
  jennie: 'Like Jennie',
  jisoo: 'Flower',
  rose: 'Number One Girl',
  lisa: 'Rockstar',
};

// Neutral gray square with a music-note glyph, generated inline as an SVG
// data URI — used only to preview icon-box spacing/sizing here. Never a
// real photo of a person; in the real app this slot is filled by the
// user's own synced album art / artist image (see LevelUpCard's
// `imageUrl` prop).
const PLACEHOLDER_ICON =
  'data:image/svg+xml;utf8,' +
  encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">
      <rect width="64" height="64" fill="#3A3A42"/>
      <path d="M24 44V16l24-4v26" stroke="#C9C9D2" stroke-width="3" fill="none" stroke-linecap="round" stroke-linejoin="round"/>
      <circle cx="20" cy="46" r="6" fill="#C9C9D2"/>
      <circle cx="44" cy="40" r="6" fill="#C9C9D2"/>
    </svg>`
  );

/**
 * DEV-ONLY design preview. Renders LevelUpCard with made-up data so the
 * whole set of theme/tier/type combinations can be reviewed instantly —
 * no play_records, no real streams, no backend calls at all.
 *
 * Not linked from the Navbar on purpose; open it directly at /dev/cards.
 * Safe to delete before shipping to real users, or gate behind
 * import.meta.env.DEV if you want it to disappear from production
 * builds automatically.
 */
export default function CardPreview() {
  const [active, setActive] = useState(null); // { themeKey, tier, type }
  const [showIcon, setShowIcon] = useState(true);

  const openCard = (themeKey, tier, type) => setActive({ themeKey, tier, type });
  const closeCard = () => setActive(null);

  const theme = active ? THEMES[active.themeKey] : null;
  const level = active ? SAMPLE_LEVEL_BY_TIER[active.tier] : null;

  return (
    <div className="max-w-5xl mx-auto px-4 sm:px-6 pb-24 pt-10">
      <div className="mb-8">
        <p className="text-xs uppercase tracking-[0.2em] text-violet-bright font-semibold mb-1">
          Developer Tool
        </p>
        <h1 className="font-display text-2xl sm:text-3xl font-semibold text-fg">Level-Up Card Preview</h1>
        <p className="text-sm text-mist mt-2 max-w-xl">
          Click any cell to pop that exact card with placeholder data — no streams, no login, no backend
          calls. Use this to iterate on colors/copy/layout in LevelUpCard.jsx and artistThemes.js.
        </p>

        <label className="inline-flex items-center gap-2 mt-4 text-xs text-mist cursor-pointer">
          <input type="checkbox" checked={showIcon} onChange={(e) => setShowIcon(e.target.checked)} />
          Show a placeholder icon (to preview spacing with a real image in place)
        </label>
      </div>

      <div className="overflow-x-auto -mx-4 px-4 sm:mx-0 sm:px-0">
        <table className="w-full min-w-[640px] border-separate border-spacing-2">
          <thead>
            <tr className="text-mist text-xs uppercase tracking-[0.15em]">
              <th className="text-left font-medium px-2 pb-2">Theme</th>
              {TIER_OPTIONS.map((tier) => (
                <th key={tier} className="text-center font-medium px-2 pb-2">
                  {tier}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {Object.values(THEMES).map((t) => (
              <tr key={t.key}>
                <td className="px-2 py-2 text-fg text-sm font-medium whitespace-nowrap">{t.label}</td>
                {TIER_OPTIONS.map((tier) => (
                  <td key={tier} className="px-2 py-2">
                    <div className="flex flex-col gap-1.5">
                      <button
                        onClick={() => openCard(t.key, tier, t.kind === 'group' ? 'artist' : 'solo')}
                        className="text-xs rounded-lg px-3 py-2 border transition-colors hover:opacity-80"
                        style={{
                          background: t.accentSoft,
                          borderColor: t.border,
                          color: t.accent,
                        }}
                      >
                        {t.kind === 'group' ? 'Group overall' : 'Solo overall'}
                      </button>
                      {/* Every theme gets a Song card option now,
                          BLACKPINK included — a group song (e.g. "Jump")
                          gets its own Song Milestone card distinct from
                          the group's combined-total card above, not just
                          member solo songs. */}
                      <button
                        onClick={() => openCard(t.key, tier, 'song')}
                        className="text-xs rounded-lg px-3 py-2 border transition-colors hover:opacity-80"
                        style={{
                          background: t.accentSoft,
                          borderColor: t.border,
                          color: t.accent,
                        }}
                      >
                        Song card
                      </button>
                    </div>
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      {active && theme && (
        <LevelUpCard
          type={active.type === 'song' ? 'solo' : active.type}
          theme={theme}
          level={level}
          tier={active.tier}
          totalStreams={level * 12345}
          progress={0.62}
          artistName={theme.kind === 'group' ? 'BLACKPINK' : theme.label}
          memberName={theme.kind === 'solo' ? theme.label : undefined}
          songTitle={active.type === 'song' ? SONGS_BY_MEMBER[theme.key] : undefined}
          imageUrl={showIcon ? PLACEHOLDER_ICON : undefined}
          onClose={closeCard}
        />
      )}
    </div>
  );
}
