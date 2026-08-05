import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../lib/AuthContext';
import AuthShell from '../components/AuthShell';

export default function Login() {
  const { login } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ email: '', password: '' });
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);

  const submit = async (e) => {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await login(form.email, form.password);
      navigate('/dashboard');
    } catch (err) {
      setError(err.response?.data?.message || 'Log in failed. Check your details and try again.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <AuthShell title="Welcome back" subtitle="Log in to see your personal stream stats">
      <form onSubmit={submit} className="flex flex-col gap-4">
        <input
          type="email"
          required
          placeholder="Email"
          value={form.email}
          onChange={(e) => setForm({ ...form, email: e.target.value })}
          className="bg-ink-surface border border-ink-border rounded-xl px-4 py-3 text-sm outline-none focus:border-violet transition-colors"
        />
        <input
          type="password"
          required
          placeholder="Password"
          value={form.password}
          onChange={(e) => setForm({ ...form, password: e.target.value })}
          className="bg-ink-surface border border-ink-border rounded-xl px-4 py-3 text-sm outline-none focus:border-violet transition-colors"
        />
        {error && <p className="text-sm text-apple">{error}</p>}
        <button
          disabled={busy}
          className="bg-aurora rounded-xl py-3 font-medium hover:opacity-90 transition-opacity disabled:opacity-50"
        >
          {busy ? 'Logging in…' : 'Log in'}
        </button>
      </form>
      <p className="text-center text-sm text-mist mt-6">
        No account yet?{' '}
        <Link to="/register" className="text-white underline underline-offset-4">
          Create one
        </Link>
      </p>
    </AuthShell>
  );
}
