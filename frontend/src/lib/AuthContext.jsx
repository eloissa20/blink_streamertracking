import { createContext, useContext, useEffect, useState, useCallback } from 'react';
import api from '../api/client';

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [hasConnectedStatsFm, setHasConnectedStatsFm] = useState(false);
  const [hasConnectedMusicat, setHasConnectedMusicat] = useState(false);
  const [loading, setLoading] = useState(true);

  const refresh = useCallback(async () => {
    const token = localStorage.getItem('auth_token');
    if (!token) {
      setUser(null);
      setLoading(false);
      return;
    }
    try {
      const { data } = await api.get('/auth/me');
      setUser(data.user);
      setHasConnectedStatsFm(data.has_connected_statsfm);
      setHasConnectedMusicat(data.has_connected_musicat);
    } catch (err) {
      // Only treat this as "logged out" when the server actually rejected
      // the token (401). Network errors, timeouts, or a backend that's
      // still booting should NOT force the user back to the login screen.
      if (err.response?.status === 401) {
        localStorage.removeItem('auth_token');
        setUser(null);
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    refresh();
  }, [refresh]);

  const login = async (email, password) => {
    const { data } = await api.post('/auth/login', { email, password });
    localStorage.setItem('auth_token', data.token);
    setUser(data.user);
    await refresh();
  };

  const register = async (name, email, password, password_confirmation) => {
    const { data } = await api.post('/auth/register', { name, email, password, password_confirmation });
    localStorage.setItem('auth_token', data.token);
    setUser(data.user);
    await refresh();
  };

  const logout = async () => {
    try {
      await api.post('/auth/logout');
    } catch {
      // ignore — clearing local state regardless
    }
    localStorage.removeItem('auth_token');
    setUser(null);
    setHasConnectedStatsFm(false);
    setHasConnectedMusicat(false);
  };

  const hasAnyConnection = hasConnectedStatsFm || hasConnectedMusicat;

  return (
    <AuthContext.Provider
      value={{
        user,
        hasConnectedStatsFm,
        hasConnectedMusicat,
        hasAnyConnection,
        loading,
        login,
        register,
        logout,
        refresh,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  return useContext(AuthContext);
}
