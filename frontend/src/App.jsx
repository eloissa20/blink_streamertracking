import { Routes, Route, Navigate } from 'react-router-dom';
import { useAuth } from './lib/AuthContext';
import Navbar from './components/Navbar';
import Waveform from './components/Waveform';
import LandingPage from './pages/LandingPage';
import Login from './pages/Login';
import Register from './pages/Register';
import Connect from './pages/Connect';
import Dashboard from './pages/Dashboard';

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

function RequireStatsFm({ children }) {
  const { user, hasConnectedStatsFm, loading } = useAuth();
  if (loading) return <FullScreenLoader />;
  if (!user) return <Navigate to="/login" replace />;
  if (!hasConnectedStatsFm) return <Navigate to="/connect" replace />;
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
            <RequireStatsFm>
              <Dashboard />
            </RequireStatsFm>
          }
        />
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </>
  );
}
