export default function SourceBadge({ source }) {
  const isApple = source === 'apple_music';
  return (
    <span
      className={`text-[10px] uppercase tracking-wider font-semibold px-2 py-0.5 rounded-full border ${
        isApple
          ? 'text-apple border-apple/40 bg-apple/10'
          : 'text-spotify border-spotify/40 bg-spotify/10'
      }`}
    >
      {isApple ? 'Apple Music' : 'Spotify'}
    </span>
  );
}
