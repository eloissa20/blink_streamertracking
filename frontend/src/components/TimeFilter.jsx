const OPTIONS = [
  { key: 'day', label: 'Day' },
  { key: 'week', label: 'Week' },
  { key: 'month', label: 'Month' },
  { key: 'year', label: 'Year' },
];

export default function TimeFilter({ value, onChange }) {
  return (
    <div className="inline-flex glass-card rounded-full p-1 gap-1">
      {OPTIONS.map((opt) => (
        <button
          key={opt.key}
          onClick={() => onChange(opt.key)}
          className={`relative px-3 py-1 sm:px-4 sm:py-1.5 rounded-full text-xs sm:text-sm font-medium transition-colors ${
            value === opt.key ? 'text-white' : 'text-mist hover:text-white'
          }`}
        >
          {value === opt.key && (
            <span className="absolute inset-0 rounded-full bg-aurora -z-10" />
          )}
          {opt.label}
        </button>
      ))}
    </div>
  );
}
