import { useState, useRef, useEffect } from 'react';
import { SpotifyIcon } from './BrandIcons';

/**
 * Lets the user see which of their connected Spotify accounts is
 * currently active and switch to another one. Only renders anything
 * meaningful once there's more than one connection — with zero or one,
 * there's nothing to switch between, so it stays out of the way.
 */
export default function SpotifyAccountSwitcher({ connections, activeId, onChange }) {
  const [open, setOpen] = useState(false);
  const rootRef = useRef(null);

  useEffect(() => {
    const onClickOutside = (e) => {
      if (rootRef.current && !rootRef.current.contains(e.target)) {
        setOpen(false);
      }
    };
    document.addEventListener('mousedown', onClickOutside);
    return () => document.removeEventListener('mousedown', onClickOutside);
  }, []);

  if (!connections || connections.length <= 1) {
    return null;
  }

  const active = connections.find((c) => c.id === activeId) ?? connections[0];

  return (
    <div className="relative" ref={rootRef}>
      <button
        onClick={() => setOpen((v) => !v)}
        className="flex items-center gap-2 rounded-full border border-white/10 hover:border-white/30 px-3 py-2 text-sm transition-colors"
      >
        <SpotifyIcon className="w-4 h-4 text-spotify shrink-0" />
        <span className="truncate max-w-[9rem]">
          {active.label || active.statsfm_username}
        </span>
        <span className="text-mist text-xs">
          ({connections.length})
        </span>
      </button>

      {open && (
        <div className="absolute right-0 z-20 mt-2 w-64 glass-card rounded-2xl p-2">
          <p className="text-[10px] uppercase tracking-[0.2em] text-mist px-3 py-1.5">
            Spotify accounts
          </p>
          <div className="max-h-72 overflow-y-auto flex flex-col gap-0.5">
            {connections.map((c) => (
              <button
                key={c.id}
                onClick={() => {
                  onChange(c.id);
                  setOpen(false);
                }}
                className={`flex items-center gap-2 rounded-xl px-3 py-2 text-left text-sm transition-colors ${
                  c.id === active.id ? 'bg-aurora text-white' : 'hover:bg-white/5 text-mist hover:text-white'
                }`}
              >
                <SpotifyIcon className="w-4 h-4 shrink-0" />
                <span className="truncate flex-1">{c.label || c.statsfm_username}</span>
                {c.id === active.id && <span className="text-[10px] uppercase tracking-wide">Active</span>}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
