import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../lib/AuthContext';
import AuthShell from '../components/AuthShell';

export default function Register() {
  const { register } = useAuth();
  const navigate = useNavigate();
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' });
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);

  const submit = async (e) => {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await register(form.name, form.email, form.password, form.password_confirmation);
      navigate('/connect');
    } catch (err) {
      const errors = err.response?.data?.errors;
      const firstError = errors ? Object.values(errors)[0]?.[0] : null;
      setError(firstError || 'Could not create your account. Please try again.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <AuthShell title="Create your account" subtitle="Then connect a Stats.fm account to unlock your personal stats">
      <form onSubmit={submit} className="flex flex-col gap-4">
        <input
          type="text"
          required
          placeholder="Full name"
          value={form.name}
          onChange={(e) => setForm({ ...form, name: e.target.value })}
          className="bg-ink-surface border border-ink-border rounded-xl px-4 py-3 text-sm outline-none focus:border-violet transition-colors"
        />
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
          placeholder="Password (min 8 characters)"
          value={form.password}
          onChange={(e) => setForm({ ...form, password: e.target.value })}
          className="bg-ink-surface border border-ink-border rounded-xl px-4 py-3 text-sm outline-none focus:border-violet transition-colors"
        />
        <input
          type="password"
          required
          placeholder="Confirm password"
          value={form.password_confirmation}
          onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })}
          className="bg-ink-surface border border-ink-border rounded-xl px-4 py-3 text-sm outline-none focus:border-violet transition-colors"
        />
        {error && <p className="text-sm text-apple">{error}</p>}
        <button
          disabled={busy}
          className="bg-aurora rounded-xl py-3 font-medium hover:opacity-90 transition-opacity disabled:opacity-50"
        >
          {busy ? 'Creating account…' : 'Create account'}
        </button>
      </form>
      <p className="text-center text-sm text-mist mt-6">
        Already have an account?{' '}
        <Link to="/login" className="text-white underline underline-offset-4">
          Log in
        </Link>
      </p>
    </AuthShell>
  );
}
