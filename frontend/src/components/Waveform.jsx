export default function Waveform({ bars = 5, className = '' }) {
  return (
    <span className={`waveform ${className}`} aria-hidden="true">
      {Array.from({ length: bars }).map((_, i) => (
        <span key={i} />
      ))}
    </span>
  );
}
