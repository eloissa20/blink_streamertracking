import { motion } from 'framer-motion';
import SourceBadge from './SourceBadge';

function timeAgo(iso) {
  const diffMs = Date.now() - new Date(iso).getTime();
  const mins = Math.floor(diffMs / 60000);
  if (mins < 1) return 'just now';
  if (mins < 60) return `${mins}m ago`;
  const hours = Math.floor(mins / 60);
  if (hours < 24) return `${hours}h ago`;
  const days = Math.floor(hours / 24);
  return `${days}d ago`;
}

export default function RecentlyPlayedItem({ item, index = 0 }) {
  return (
    <motion.div
      initial={{ opacity: 0, x: -10 }}
      animate={{ opacity: 1, x: 0 }}
      transition={{ duration: 0.3, delay: Math.min(index * 0.02, 0.6) }}
      className="flex items-center gap-4 rounded-xl px-4 py-3 hover:bg-white/5 transition-colors"
    >
      <div className="w-10 h-10 rounded-lg bg-ink-surface flex-shrink-0 overflow-hidden">
        {item.artwork_url ? (
          <img src={item.artwork_url} alt="" className="w-full h-full object-cover" />
        ) : (
          <div className="w-full h-full bg-aurora-soft" />
        )}
      </div>
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-medium text-white">{item.track_name}</p>
        <p className="truncate text-xs text-mist">{item.artist_name}</p>
      </div>
      <SourceBadge source={item.source} />
      <span className="font-mono tabular text-xs text-mist whitespace-nowrap">
        {item.duration_formatted}
      </span>
      <span className="text-xs text-mist whitespace-nowrap w-16 text-right">
        {timeAgo(item.played_at)}
      </span>
    </motion.div>
  );
}
