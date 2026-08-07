import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import api from '../api/client';
import StatCard from '../components/StatCard';
import Section from '../components/Section';
import TrackRow from '../components/TrackRow';
import ArtistRow from '../components/ArtistRow';
import Waveform from '../components/Waveform';

export default function LandingPage() {
  const [overview, setOverview] = useState(null);
  const [tracks, setTracks] = useState([]);
  const [artists, setArtists] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    let mounted = true;

    Promise.all([
      api.get('/public/overview'),
      api.get('/public/top-tracks'),
      api.get('/public/top-artists'),
    ])
      .then(([ov, tr, ar]) => {
        if (!mounted) return;
        setOverview(ov.data);
        setTracks(tr.data.tracks);
        setArtists(ar.data.artists);
      })
      .catch(() => mounted && setError('Could not reach the API. Is the Laravel backend running?'))
      .finally(() => mounted && setLoading(false));

    return () => { mounted = false; };
  }, []);

  const totals = overview?.total_streams;

  return (
    <div className="max-w-6xl mx-auto px-4 sm:px-6 pb-24">
      {/* Hero */}
      <section className="pt-12 sm:pt-20 pb-10 sm:pb-14 text-center">
        <motion.div
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6 }}
          className="inline-flex items-center gap-2 text-xs uppercase tracking-[0.25em] text-violet-bright font-semibold mb-5"
        >
          <Waveform bars={3} />
          Live · Spotify &amp; Apple Music
        </motion.div>

        <motion.h1
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6, delay: 0.05 }}
          className="font-display text-3xl sm:text-4xl md:text-6xl font-semibold tracking-tight text-white"
        >
          Philippines Stream Overview
        </motion.h1>
        <motion.p
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.6, delay: 0.1 }}
          className="mt-4 text-mist text-base sm:text-lg max-w-xl mx-auto px-2"
        >
          Every stream, tracked in one place — combined from listeners who connected their Stats.fm account.
        </motion.p>
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
        <div className="grid md:grid-cols-2 gap-4 sm:gap-6">
          <Section eyebrow="Ranked" title="Top Tracks">
            <div className="flex flex-col gap-0.5 max-h-[520px] overflow-y-auto dark-scrollbar pr-2">
              {tracks.length === 0 && (
                <p className="text-mist text-sm py-6 text-center">No streams recorded yet.</p>
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
                <p className="text-mist text-sm py-6 text-center">No streams recorded yet.</p>
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
      )}
    </div>
  );
}
