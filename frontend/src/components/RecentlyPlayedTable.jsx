import { motion } from 'framer-motion';
import { formatFullDateTime } from '../lib/time';

export default function RecentlyPlayedTable({ items, emptyLabel }) {
  return (
    <div className="flex flex-col gap-2 max-h-[420px] overflow-y-auto dark-scrollbar pr-1">
      {items.length === 0 && (
        <p className="text-mist text-sm py-6 text-center">{emptyLabel}</p>
      )}
      {items.map((item, i) => (
        <motion.div
          key={`${item.track_id}-${item.played_at}`}
          initial={{ opacity: 0, x: -10 }}
          animate={{ opacity: 1, x: 0 }}
          transition={{ duration: 0.3, delay: Math.min(i * 0.02, 0.6) }}
          className="flex items-center gap-2 sm:gap-3 rounded-2xl bg-ink-surface/60 px-2 sm:px-3 py-2 sm:py-2.5 hover:bg-fg/5 transition-colors"
        >
          <div className="w-9 h-9 sm:w-11 sm:h-11 rounded-lg bg-ink-surface flex-shrink-0 overflow-hidden">
            {item.artwork_url ? (
              <img src={item.artwork_url} alt="" className="w-full h-full object-cover" />
            ) : (
              <div
                className="w-full h-full flex items-center justify-center transition-[background] duration-500"
                style={{
                  background:
                    'linear-gradient(120deg, var(--theme-accent-soft, rgba(89,178,146,0.15)) 0%, var(--theme-accent-soft-2, rgba(255,201,77,0.15)) 100%)',
                }}
              >
                <span className="font-display font-semibold text-xs text-fg">
                  {item.track_name?.[0]?.toUpperCase() ?? '?'}
                </span>
              </div>
            )}
          </div>

          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-semibold text-fg">{item.track_name}</p>
            <p className="truncate text-xs text-mist">{item.artist_name}</p>
          </div>

          <span className="text-[10px] sm:text-xs text-mist whitespace-nowrap font-mono tabular flex-shrink-0">
            {formatFullDateTime(item.played_at)}
          </span>
        </motion.div>
      ))}
    </div>
  );
}
