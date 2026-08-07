import { motion } from 'framer-motion';

export default function ArtistRow({ rank, artist, metric, index = 0 }) {
  return (
    <motion.div
      initial={{ opacity: 0, x: -10 }}
      animate={{ opacity: 1, x: 0 }}
      transition={{ duration: 0.35, delay: index * 0.03 }}
      className="group flex items-center gap-2 sm:gap-4 rounded-xl px-2 sm:px-4 py-2.5 sm:py-3 hover:bg-white/5 transition-colors"
    >
      <span className="font-mono text-mist text-xs sm:text-sm w-4 sm:w-6 text-right tabular flex-shrink-0">{rank}</span>

      <div className="w-9 h-9 sm:w-11 sm:h-11 rounded-full bg-aurora flex-shrink-0 flex items-center justify-center font-display font-semibold text-sm overflow-hidden">
        {artist.artist_image_url ? (
          <img src={artist.artist_image_url} alt="" className="w-full h-full object-cover" />
        ) : (
          artist.artist_name?.[0]?.toUpperCase() ?? '?'
        )}
      </div>

      <div className="min-w-0 flex-1">
        <p className="truncate text-sm sm:text-base font-medium text-white">{artist.artist_name}</p>
        {artist.track_count != null && (
          <p className="truncate text-xs sm:text-sm text-mist">{artist.track_count} tracks · combined</p>
        )}
      </div>

      <span className="font-mono tabular text-xs sm:text-sm text-mist group-hover:text-white transition-colors whitespace-nowrap flex-shrink-0">
        {metric}
      </span>
    </motion.div>
  );
}
