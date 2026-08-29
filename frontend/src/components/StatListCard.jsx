import { motion } from 'framer-motion';

export default function StatListCard({
  title,
  items,
  emptyLabel,
  renderPrimary,
  renderMetric,
  renderImage,
  imageShape = 'square',
  delay = 0,
}) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.5, delay, ease: [0.16, 1, 0.3, 1] }}
      className="glass-card rounded-3xl p-4 sm:p-6 md:p-8 flex flex-col"
    >
      <h2 className="font-display text-xl sm:text-2xl font-semibold text-white mb-4">{title}</h2>

      <div className="flex-1 max-h-[420px] overflow-y-auto dark-scrollbar pr-2">
        {items.length === 0 && (
          <p className="text-mist text-sm py-6 text-center">{emptyLabel}</p>
        )}
        <div className="flex flex-col gap-0.5">
          {items.map((item, i) => {
            const imageUrl = renderImage ? renderImage(item) : null;
            return (
              <motion.div
                key={i}
                initial={{ opacity: 0, x: -10 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ duration: 0.3, delay: Math.min(i * 0.02, 0.6) }}
                className="flex items-center justify-between gap-2 sm:gap-3 rounded-xl px-2 sm:px-3 py-2.5 sm:py-3 hover:bg-white/5 transition-colors"
              >
                <div className="flex items-center gap-2 sm:gap-3 min-w-0">
                  <span className="font-mono text-mist text-xs w-5 text-right tabular flex-shrink-0">
                    {i + 1}
                  </span>

                  {renderImage && (
                    <div
                      className={`w-9 h-9 sm:w-10 sm:h-10 flex-shrink-0 overflow-hidden bg-aurora-soft flex items-center justify-center ${
                        imageShape === 'circle' ? 'rounded-full' : 'rounded-lg'
                      }`}
                    >
                      {imageUrl ? (
                        <img src={imageUrl} alt="" className="w-full h-full object-cover" />
                      ) : (
                        <span className="font-display font-semibold text-xs text-white">
                          {renderPrimary(item)?.[0]?.toUpperCase() ?? '?'}
                        </span>
                      )}
                    </div>
                  )}

                  <span className="truncate text-sm sm:text-base text-white font-medium">{renderPrimary(item)}</span>
                </div>
                <span className="font-mono tabular text-xs sm:text-sm text-mist whitespace-nowrap flex-shrink-0">
                  {renderMetric(item)}
                </span>
              </motion.div>
            );
          })}
        </div>
      </div>
    </motion.div>
  );
}
