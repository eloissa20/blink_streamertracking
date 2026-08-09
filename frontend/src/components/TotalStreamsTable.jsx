import { motion } from 'framer-motion';
import DailyChangeIndicator from './DailyChangeIndicator';

export default function TotalStreamsTable({ tracks, emptyLabel }) {
  if (tracks.length === 0) {
    return <p className="text-mist text-sm py-6 text-center">{emptyLabel}</p>;
  }

  return (
    <div className="overflow-x-auto -mx-2 sm:mx-0">
      <table className="w-full min-w-[520px] border-separate border-spacing-y-0.5">
        <thead>
          <tr className="text-mist text-[10px] sm:text-xs uppercase tracking-[0.15em] font-medium">
            <th className="text-left font-medium px-2 sm:px-4 pb-2 w-10">#</th>
            <th className="text-left font-medium px-2 sm:px-4 pb-2">Track</th>
            <th className="text-right font-medium px-2 sm:px-4 pb-2">Total Streams</th>
            <th className="text-right font-medium px-2 sm:px-4 pb-2">Today vs. Yesterday</th>
          </tr>
        </thead>
        <tbody>
          {tracks.map((t, i) => (
            <motion.tr
              key={t.track_id}
              initial={{ opacity: 0, x: -10 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ duration: 0.35, delay: Math.min(i * 0.02, 0.5) }}
              className="group hover:bg-white/5 transition-colors"
            >
              <td className="px-2 sm:px-4 py-2.5 sm:py-3 rounded-l-xl">
                <span className="font-mono text-mist text-xs sm:text-sm tabular">{i + 1}</span>
              </td>
              <td className="px-2 sm:px-4 py-2.5 sm:py-3 min-w-0">
                <div className="flex items-center gap-2 sm:gap-3 min-w-0">
                  <div className="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-ink-surface flex-shrink-0 overflow-hidden">
                    {t.artwork_url ? (
                      <img src={t.artwork_url} alt="" className="w-full h-full object-cover" />
                    ) : (
                      <div
                        className="w-full h-full flex items-center justify-center transition-[background] duration-500"
                        style={{
                          background:
                            'linear-gradient(120deg, var(--theme-accent-soft, rgba(89,178,146,0.15)) 0%, var(--theme-accent-soft-2, rgba(255,201,77,0.15)) 100%)',
                        }}
                      >
                        <span className="font-display font-semibold text-[10px] text-white">
                          {t.track_name?.[0]?.toUpperCase() ?? '?'}
                        </span>
                      </div>
                    )}
                  </div>
                  <div className="min-w-0">
                    <p className="truncate text-sm font-medium text-white">{t.track_name}</p>
                    <p className="truncate text-xs text-mist">{t.artist_name}</p>
                  </div>
                </div>
              </td>
              <td className="px-2 sm:px-4 py-2.5 sm:py-3 text-right">
                <span className="font-mono tabular text-sm sm:text-base font-semibold text-white">
                  {Number(t.stream_count).toLocaleString()}
                </span>
              </td>
              <td className="px-2 sm:px-4 py-2.5 sm:py-3 rounded-r-xl text-right">
                <DailyChangeIndicator today={t.today_count ?? 0} yesterday={t.yesterday_count ?? 0} />
              </td>
            </motion.tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
