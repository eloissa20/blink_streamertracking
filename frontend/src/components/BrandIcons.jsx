import appleMusicIconSrc from '../images/Apple_Music_icon.svg.webp';

export function SpotifyIcon({ className = 'w-5 h-5' }) {
  return (
    <svg viewBox="0 0 24 24" className={className} fill="currentColor" aria-hidden="true">
      <path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm4.59 14.44a.62.62 0 0 1-.86.21c-2.36-1.44-5.33-1.77-8.83-.97a.63.63 0 0 1-.28-1.22c3.83-.88 7.12-.5 9.76 1.11.3.18.4.57.21.87Zm1.22-2.72a.78.78 0 0 1-1.07.26c-2.7-1.66-6.82-2.14-10.02-1.17a.78.78 0 1 1-.45-1.5c3.65-1.1 8.19-.57 11.28 1.33.37.23.48.72.26 1.08Zm.11-2.83c-3.24-1.92-8.6-2.1-11.7-1.16a.94.94 0 1 1-.55-1.8c3.56-1.08 9.47-.87 13.2 1.35a.94.94 0 0 1-.95 1.61Z" />
    </svg>
  );
}

// Single source of truth for the Apple Music mark across the whole app —
// every place that shows the icon (sidebar/nav, headers, public stats,
// missions, achievements, my stats, buttons, cards, loading/empty states)
// imports this component rather than drawing its own SVG, so swapping the
// asset here updates it everywhere at once.
export function AppleMusicIcon({ className = 'w-5 h-5' }) {
  return (
    <img
      src={appleMusicIconSrc}
      alt="Apple Music"
      className={`${className} object-contain rounded-[22%]`}
      draggable={false}
    />
  );
}
