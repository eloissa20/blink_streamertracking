import { motion } from 'framer-motion';
import SourceBadge from './SourceBadge';

export default function TrackRow({ rank, track, metric, index = 0 }) {
  return (
    <motion.div
      initial={{ opacity: 0, x: -10 }}
      animate={{ opacity: 1, x: 0 }}
      transition={{ duration: 0.35, delay: index * 0.03 }}
      className="group flex items-center gap-4 rounded-xl px-4 py-3 hover:bg-white/5 transition-colors"
    >
      <span className="font-mono text-mist text-sm w-6 text-right tabular">{rank}</span>

      <div className="w-11 h-11 rounded-lg bg-ink-surface flex-shrink-0 overflow-hidden">
        {track.artwork_url ? (
          <img src={track.artwork_url} alt="" className="w-full h-full object-cover" />
        ) : (
          <div className="w-full h-full bg-aurora-soft" />
        )}
      </div>

      <div className="min-w-0 flex-1">
        <p className="truncate font-medium text-white">{track.track_name}</p>
        <p className="truncate text-sm text-mist">{track.artist_name}</p>
      </div>

      {track.source && <SourceBadge source={track.source} />}

      <span className="font-mono tabular text-sm text-mist group-hover:text-white transition-colors whitespace-nowrap">
        {metric}
      </span>
    </motion.div>
  );
}
