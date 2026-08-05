import { motion } from 'framer-motion';

export default function ArtistRow({ rank, artist, metric, index = 0 }) {
  return (
    <motion.div
      initial={{ opacity: 0, x: -10 }}
      animate={{ opacity: 1, x: 0 }}
      transition={{ duration: 0.35, delay: index * 0.03 }}
      className="group flex items-center gap-4 rounded-xl px-4 py-3 hover:bg-white/5 transition-colors"
    >
      <span className="font-mono text-mist text-sm w-6 text-right tabular">{rank}</span>

      <div className="w-11 h-11 rounded-full bg-aurora flex-shrink-0 flex items-center justify-center font-display font-semibold text-sm overflow-hidden">
        {artist.artist_image_url ? (
          <img src={artist.artist_image_url} alt="" className="w-full h-full object-cover" />
        ) : (
          artist.artist_name?.[0]?.toUpperCase() ?? '?'
        )}
      </div>

      <div className="min-w-0 flex-1">
        <p className="truncate font-medium text-white">{artist.artist_name}</p>
        {artist.track_count != null && (
          <p className="truncate text-sm text-mist">{artist.track_count} tracks · combined</p>
        )}
      </div>

      <span className="font-mono tabular text-sm text-mist group-hover:text-white transition-colors whitespace-nowrap">
        {metric}
      </span>
    </motion.div>
  );
}
