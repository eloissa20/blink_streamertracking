import { SpotifyIcon, AppleMusicIcon } from './BrandIcons';

const PLATFORMS = [
  {
    key: 'spotify',
    label: 'Spotify',
    poweredBy: 'powered by statsfm',
    Icon: SpotifyIcon,
    activeClass: 'text-spotify',
  },
  {
    key: 'apple_music',
    label: 'Apple Music',
    poweredBy: 'powered by musicat',
    Icon: AppleMusicIcon,
    activeClass: 'text-apple',
  },
];

export default function PlatformTabs({ value, onChange }) {
  return (
    <div className="inline-flex glass-card rounded-full p-1 gap-1 mx-auto">
      {PLATFORMS.map(({ key, label, poweredBy, Icon, activeClass }) => {
        const active = value === key;
        return (
          <button
            key={key}
            onClick={() => onChange(key)}
            className={`relative flex items-center gap-2 px-4 py-2 sm:px-6 sm:py-2.5 rounded-full text-xs sm:text-sm font-semibold uppercase tracking-wide transition-colors ${
              active ? 'text-white' : 'text-mist hover:text-white'
            }`}
          >
            {active && (
              <span className="absolute inset-0 rounded-full bg-aurora -z-10" />
            )}
            <Icon className={`w-4 h-4 sm:w-5 sm:h-5 ${active ? activeClass : ''}`} />
            <span className="flex flex-col items-start leading-tight">
              <span>{label}</span>
              <span className="text-[9px] sm:text-[10px] normal-case tracking-normal font-normal opacity-70">
                {poweredBy}
              </span>
            </span>
          </button>
        );
      })}
    </div>
  );
}
