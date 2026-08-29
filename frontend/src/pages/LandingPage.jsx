import { useEffect, useState, useCallback } from 'react';
import { motion } from 'framer-motion';
import api from '../api/client';
import StatCard from '../components/StatCard';
import Section from '../components/Section';
import TrackRow from '../components/TrackRow';
import ArtistRow from '../components/ArtistRow';
import RecentlyPlayedTable from '../components/RecentlyPlayedTable';
import PlatformTabs from '../components/PlatformTabs';
import Waveform from '../components/Waveform';
import TotalStreamsTable from '../components/TotalStreamsTable';

const PLATFORM_COPY = {
  spotify: {
    title: 'Philippines Spotify Stream',
    tagline: "Every stream, tracked in one place — powered by Stats.fm's Spotify data.",
    emptyLabel: 'No Spotify streams recorded yet.',
    recentEmptyLabel: 'No recent Spotify plays yet.',
  },
  apple_music: {
    title: 'Philippines Apple Music Stream',
    tagline: "Every stream, tracked in one place — powered by Musicat's Apple Music data.",
    emptyLabel: 'No Apple Music streams recorded yet.',
    recentEmptyLabel: 'No recent Apple Music plays yet.',
  },
};

export default function LandingPage() {
  const [platform, setPlatform] = useState('spotify');

  // Each tab's data is fetched fresh and kept in its own state slot, so
  // switching tabs never mixes Spotify and Apple Music numbers together.
  const [overview, setOverview] = useState(null);
  const [tracks, setTracks] = useState([]);
  const [artists, setArtists] = useState([]);
  const [recentlyPlayed, setRecentlyPlayed] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const load = useCallback((platformKey) => {
    let mounted = true;
    setLoading(true);
    setError(null);

    Promise.all([
      api.get('/public/overview', { params: { platform: platformKey } }),
      api.get('/public/top-tracks', { params: { platform: platformKey } }),
      api.get('/public/top-artists', { params: { platform: platformKey } }),
      api.get('/public/recently-played', { params: { platform: platformKey } }),
    ])
      .then(([ov, tr, ar, rp]) => {
        if (!mounted) return;
        setOverview(ov.data);
        setTracks(tr.data.tracks);
        setArtists(ar.data.artists);
        setRecentlyPlayed(rp.data.recently_played);
      })
      .catch(() => mounted && setError('Could not reach the API. Is the Laravel backend running?'))
      .finally(() => mounted && setLoading(false));

    return () => {
      mounted = false;
    };
  }, []);

  useEffect(() => {
    const cleanup = load(platform);
    return cleanup;
  }, [platform, load]);

  const totals = overview?.total_streams;
  const copy = PLATFORM_COPY[platform];
  // Drives every themed CSS var in index.css (background, accents, card
  // radius, scrollbars, waveform) — see the "Platform theming" block
  // there for what each value controls.
  const themeKey = platform === 'apple_music' ? 'apple_music' : 'spotify';

  return (
    <div data-theme={themeKey} className="theme-surface min-h-screen">
      <div className="max-w-6xl mx-auto px-4 sm:px-6 pb-24">
      {/* Hero */}
      <section className="pt-12 sm:pt-20 pb-10 sm:pb-14 text-center">
        <motion.div
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6 }}
          className="inline-flex items-center gap-2 text-xs uppercase tracking-[0.25em] font-semibold mb-5 transition-colors duration-500"
          style={{ color: 'var(--theme-accent-strong)' }}
        >
          <Waveform bars={3} />
          Live · Spotify &amp; Apple Music
        </motion.div>

        <motion.h1
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6, delay: 0.05 }}
          className="font-display text-3xl sm:text-4xl md:text-6xl font-semibold tracking-tight text-fg"
        >
          {copy.title}
        </motion.h1>
        <motion.p
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6, delay: 0.1 }}
          className="mt-4 text-mist text-base sm:text-lg max-w-xl mx-auto px-2"
        >
          {copy.tagline}
        </motion.p>

        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6, delay: 0.15 }}
          className="mt-8 flex justify-center"
        >
          <PlatformTabs value={platform} onChange={setPlatform} />
        </motion.div>
      </section>

      {error && (
        <div className="glass-card rounded-2xl p-6 mb-8 text-center text-sm text-mist">{error}</div>
      )}

      {/* Hero stats */}
      <div className="grid grid-cols-2 md:grid-cols-5 gap-3 sm:gap-4 mb-10">
        <div className="col-span-2 md:col-span-1">
          <StatCard label="All-time" value={totals?.all_time ?? 0} delay={0} accent />
        </div>
        <StatCard label="Today" value={totals?.today ?? 0} delay={0.05} />
        <StatCard label="This Week" value={totals?.this_week ?? 0} delay={0.1} />
        <StatCard label="This Month" value={totals?.this_month ?? 0} delay={0.15} />
        <StatCard label="This Year" value={totals?.this_year ?? 0} delay={0.2} />
      </div>

      {loading && (
        <div className="flex justify-center py-16">
          <Waveform bars={5} className="scale-150" />
        </div>
      )}

      {!loading && (
        <div className="flex flex-col gap-4 sm:gap-6">
          <div className="grid md:grid-cols-2 gap-4 sm:gap-6">
            <Section eyebrow="Ranked" title="Top Tracks">
              <div className="flex flex-col gap-0.5 max-h-[520px] overflow-y-auto dark-scrollbar pr-2">
                {tracks.length === 0 && (
                  <p className="text-mist text-sm py-6 text-center">{copy.emptyLabel}</p>
                )}
                {tracks.map((t, i) => (
                  <TrackRow
                    key={t.track_id}
                    rank={i + 1}
                    track={t}
                    metric={`${Number(t.stream_count).toLocaleString()} streams`}
                    index={i}
                  />
                ))}
              </div>
            </Section>

            <Section eyebrow="Ranked" title="Top Artists">
              <div className="flex flex-col gap-0.5">
                {artists.length === 0 && (
                  <p className="text-mist text-sm py-6 text-center">{copy.emptyLabel}</p>
                )}
                {artists.map((a, i) => (
                  <ArtistRow
                    key={a.artist_id}
                    rank={i + 1}
                    artist={a}
                    metric={`${Number(a.stream_count).toLocaleString()} streams`}
                    index={i}
                  />
                ))}
              </div>
            </Section>
          </div>

          <Section eyebrow="Lifetime" title="Total Streams">
            <TotalStreamsTable tracks={tracks} emptyLabel={copy.emptyLabel} />
          </Section>
        </div>
      )}
    </div>
    </div>
  );
}
