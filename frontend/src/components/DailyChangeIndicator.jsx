/**
 * "+123 ▲" style badge showing how many streams a track picked up today,
 * with a triangle indicating whether that's trending up or down against
 * yesterday's count. Green ▲ when today beats yesterday, red ▼ when it's
 * behind, and a neutral dash when they're tied (e.g. both zero) — an
 * unclear direction shouldn't be forced into green or red.
 */
export default function DailyChangeIndicator({ today = 0, yesterday = 0 }) {
  const isUp = today > yesterday;
  const isDown = today < yesterday;

  const color = isUp
    ? '#22C55E' // green — trending up
    : isDown
      ? '#EF4444' // red — trending down
      : 'var(--theme-mist, #9C9AB8)'; // flat — no signal either way

  const arrow = isUp ? '▲' : isDown ? '▼' : '–';

  return (
    <span
      className="inline-flex items-center gap-1 font-mono tabular text-xs sm:text-sm font-semibold whitespace-nowrap transition-colors duration-500"
      style={{ color }}
      title={`+${today} today vs +${yesterday} yesterday`}
    >
      <span>+{Number(today).toLocaleString()}</span>
      <span aria-hidden="true">{arrow}</span>
    </span>
  );
}
