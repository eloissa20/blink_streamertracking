import { motion } from 'framer-motion';
import SourceBadge from './SourceBadge';

export default function TrackRow({ rank, track, metric, index = 0 }) {
  return (
    <motion.div
      initial={{ opacity: 0, x: -10 }}
      animate={{ opacity: 1, x: 0 }}
      transition={{ duration: 0.35, delay: index * 0.03 }}
      className="group flex items-center gap-2 sm:gap-4 rounded-xl px-2 sm:px-4 py-2.5 sm:py-3 hover:bg-white/5 transition-colors"
    >
      <span className="font-mono text-mist text-xs sm:text-sm w-4 sm:w-6 text-right tabular flex-shrink-0">{rank}</span>

      <div className="w-9 h-9 sm:w-11 sm:h-11 rounded-lg bg-ink-surface flex-shrink-0 overflow-hidden">
        {track.artwork_url ? (
          <img src={track.artwork_url} alt="" className="w-full h-full object-cover" />
        ) : (
          <div className="w-full h-full bg-aurora-soft" />
        )}
      </div>

      <div className="min-w-0 flex-1">
        <p className="truncate text-sm sm:text-base font-medium text-white">{track.track_name}</p>
        <p className="truncate text-xs sm:text-sm text-mist">{track.artist_name}</p>
      </div>

      {track.source && <span className="hidden sm:inline-flex flex-shrink-0"><SourceBadge source={track.source} /></span>}

      <span className="font-mono tabular text-xs sm:text-sm text-mist group-hover:text-white transition-colors whitespace-nowrap flex-shrink-0">
        {metric}
      </span>
    </motion.div>
  );
}
