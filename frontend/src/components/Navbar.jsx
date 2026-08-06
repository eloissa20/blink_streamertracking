import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../lib/AuthContext';
import Waveform from './Waveform';

export default function Navbar() {
  const { user, logout } = useAuth();
  const navigate = useNavigate();

  return (
    <header className="sticky top-0 z-40 backdrop-blur-xl bg-ink/70 border-b border-white/5">
      <div className="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
        <Link to="/" className="flex items-center gap-2.5 font-display font-semibold text-lg">
          <Waveform />
          <span>Stream<span className="text-transparent bg-clip-text bg-aurora">Overview</span></span>
        </Link>

        <nav className="flex items-center gap-3">
          {user ? (
            <>
              <Link
                to="/"
                className="text-sm text-mist hover:text-white transition-colors px-3 py-2"
              >
                Public Stats
              </Link>
              <Link
                to="/dashboard"
                className="text-sm text-mist hover:text-white transition-colors px-3 py-2"
              >
                My Stats
              </Link>
              <Link
                to="/connect"
                className="text-sm text-mist hover:text-white transition-colors px-3 py-2"
              >
                Manage Connection
              </Link>
              <button
                onClick={async () => { await logout(); navigate('/'); }}
                className="text-sm px-4 py-2 rounded-full border border-white/10 hover:border-white/30 transition-colors"
              >
                Log out
              </button>
            </>
          ) : (
            <>
              <Link
                to="/login"
                className="text-sm text-mist hover:text-white transition-colors px-3 py-2"
              >
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
      </div>
    </header>
  );
}
