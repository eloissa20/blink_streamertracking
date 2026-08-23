import { Routes, Route, Navigate } from 'react-router-dom';
import { useAuth } from './lib/AuthContext';
import Navbar from './components/Navbar';
import Waveform from './components/Waveform';
import LandingPage from './pages/LandingPage';
import Login from './pages/Login';
import Register from './pages/Register';
import Connect from './pages/Connect';
import Dashboard from './pages/Dashboard';
import Missions from './pages/Missions';
import Achievements from './pages/Achievements';
import CardPreview from './pages/CardPreview';

function FullScreenLoader() {
  return (
    <div className="min-h-screen flex items-center justify-center">
      <Waveform bars={5} className="scale-150" />
    </div>
  );
}

function RequireAuth({ children }) {
  const { user, loading } = useAuth();
  if (loading) return <FullScreenLoader />;
  if (!user) return <Navigate to="/login" replace />;
  return children;
}

function RequireConnection({ children }) {
  const { user, hasAnyConnection, loading } = useAuth();
  if (loading) return <FullScreenLoader />;
  if (!user) return <Navigate to="/login" replace />;
  // Dashboard just needs at least one source linked — Stats.fm (Spotify),
  // Musicat (Apple Music), or both.
  if (!hasAnyConnection) return <Navigate to="/connect" replace />;
  return children;
}

export default function App() {
  return (
    <>
      <Navbar />
      <Routes>
        <Route path="/" element={<LandingPage />} />
        <Route path="/login" element={<Login />} />
        <Route path="/register" element={<Register />} />
        <Route
          path="/connect"
          element={
            <RequireAuth>
              <Connect />
            </RequireAuth>
          }
        />
        <Route
          path="/dashboard"
          element={
            <RequireConnection>
              <Dashboard />
            </RequireConnection>
          }
        />
        {/* Missions only need a login, not a connected Spotify/Apple
            Music account — every user can see and follow community
            progress even before linking anything. */}
        <Route
          path="/missions"
          element={
            <RequireAuth>
              <Missions />
            </RequireAuth>
          }
        />
        <Route
          path="/achievements"
          element={
            <RequireAuth>
              <Achievements />
            </RequireAuth>
          }
        />
        {/* Dev-only design tool — not linked from the Navbar, not
            gated behind auth (it renders no real user data). Safe to
            delete this route before shipping to production if you'd
            rather it not exist at all. */}
        <Route path="/dev/cards" element={<CardPreview />} />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </>
  );
}
