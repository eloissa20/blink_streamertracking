import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../lib/AuthContext';
import Waveform from './Waveform';

export default function Navbar() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();
  const [menuOpen, setMenuOpen] = useState(false);

  const closeMenu = () => setMenuOpen(false);

  const handleLogout = async () => {
    closeMenu();
    await logout();
    navigate('/');
  };

  return (
    <header className="sticky top-0 z-40 backdrop-blur-xl bg-ink/70 border-b border-white/5">
      <div className="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between">
        <Link
          to="/"
          onClick={closeMenu}
          className="flex items-center gap-2.5 font-display font-semibold text-base sm:text-lg"
        >
          <Waveform />
          <span>Stream<span className="text-transparent bg-clip-text bg-aurora">Overview</span></span>
        </Link>

        {/* Desktop nav */}
        <nav className="hidden sm:flex items-center gap-3">
          {user ? (
            <>
              <Link to="/" className="text-sm text-mist hover:text-white transition-colors px-3 py-2">
                Public Stats
              </Link>
              <Link to="/dashboard" className="text-sm text-mist hover:text-white transition-colors px-3 py-2">
                My Stats
              </Link>
              <Link to="/missions" className="text-sm text-mist hover:text-white transition-colors px-3 py-2">
                Missions
              </Link>
              <Link to="/achievements" className="text-sm text-mist hover:text-white transition-colors px-3 py-2">
                Achievements
              </Link>
              <Link to="/connect" className="text-sm text-mist hover:text-white transition-colors px-3 py-2">
                Manage Connection
              </Link>
              <button
                onClick={handleLogout}
                className="text-sm px-4 py-2 rounded-full border border-white/10 hover:border-white/30 transition-colors"
              >
                Log out
              </button>
            </>
          ) : (
            <>
              <Link to="/login" className="text-sm text-mist hover:text-white transition-colors px-3 py-2">
                Log in
              </Link>
              <Link
                to="/register"
                className="text-sm px-4 py-2 rounded-full bg-aurora font-medium hover:opacity-90 transition-opacity"
              >
                Get started
              </Link>
            </>
          )}
        </nav>

        {/* Mobile menu toggle */}
        <button
          onClick={() => setMenuOpen((v) => !v)}
          aria-label={menuOpen ? 'Close menu' : 'Open menu'}
          aria-expanded={menuOpen}
          className="sm:hidden relative w-9 h-9 flex flex-col items-center justify-center gap-1.5 rounded-full border border-white/10"
        >
          <span
            className={`block w-4 h-[1.5px] bg-white transition-transform ${menuOpen ? 'translate-y-[3px] rotate-45' : ''}`}
          />
          <span
            className={`block w-4 h-[1.5px] bg-white transition-transform ${menuOpen ? '-translate-y-[3px] -rotate-45' : ''}`}
          />
        </button>
      </div>

      {/* Mobile nav panel */}
      {menuOpen && (
        <nav className="sm:hidden border-t border-white/5 bg-ink/95 backdrop-blur-xl px-4 py-3 flex flex-col gap-1">
          {user ? (
            <>
              <Link
                to="/"
                onClick={closeMenu}
                className="text-sm text-mist hover:text-white transition-colors px-3 py-2.5 rounded-lg hover:bg-white/5"
              >
                Public Stats
              </Link>
              <Link
                to="/dashboard"
                onClick={closeMenu}
                className="text-sm text-mist hover:text-white transition-colors px-3 py-2.5 rounded-lg hover:bg-white/5"
              >
                My Stats
              </Link>
              <Link
                to="/missions"
                onClick={closeMenu}
                className="text-sm text-mist hover:text-white transition-colors px-3 py-2.5 rounded-lg hover:bg-white/5"
              >
                Missions
              </Link>
              <Link
                to="/achievements"
                onClick={closeMenu}
                className="text-sm text-mist hover:text-white transition-colors px-3 py-2.5 rounded-lg hover:bg-white/5"
              >
                Achievements
              </Link>
              <Link
                to="/connect"
                onClick={closeMenu}
                className="text-sm text-mist hover:text-white transition-colors px-3 py-2.5 rounded-lg hover:bg-white/5"
              >
                Manage Connection
              </Link>
              <button
                onClick={handleLogout}
                className="mt-1 text-sm text-left px-3 py-2.5 rounded-lg border border-white/10 hover:border-white/30 transition-colors"
              >
                Log out
              </button>
            </>
          ) : (
            <>
              <Link
                to="/login"
                onClick={closeMenu}
                className="text-sm text-mist hover:text-white transition-colors px-3 py-2.5 rounded-lg hover:bg-white/5"
              >
                Log in
              </Link>
              <Link
                to="/register"
                onClick={closeMenu}
                className="mt-1 text-sm text-center px-4 py-2.5 rounded-full bg-aurora font-medium hover:opacity-90 transition-opacity"
              >
                Get started
              </Link>
            </>
          )}
        </nav>
      )}
    </header>
  );
}
