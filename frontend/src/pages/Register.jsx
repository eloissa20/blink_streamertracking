import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useAuth } from '../lib/AuthContext';
import AuthShell from '../components/AuthShell';

const inputClass =
  'bg-ink-surface border border-ink-border rounded-xl px-4 py-3 text-sm outline-none focus:border-violet transition-colors';

function firstError(err, fallback) {
  const errors = err.response?.data?.errors;
  if (errors) return Object.values(errors)[0]?.[0];
  return err.response?.data?.message || fallback;
}

export default function Register() {
  const { startRegistration, verifyRegistration, resendRegistrationCode } = useAuth();
  const navigate = useNavigate();

  // 'details' -> collecting name/email/password. 'code' -> entering the
  // OTP that was just emailed. No account exists yet until 'code' succeeds.
  const [step, setStep] = useState('details');
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' });
  const [code, setCode] = useState('');
  const [error, setError] = useState(null);
  const [busy, setBusy] = useState(false);
  const [cooldown, setCooldown] = useState(0);

  const timerRef = useRef(null);
  useEffect(() => {
    if (cooldown <= 0) return undefined;
    timerRef.current = setInterval(() => setCooldown((s) => Math.max(0, s - 1)), 1000);
    return () => clearInterval(timerRef.current);
  }, [cooldown]);

  const submitDetails = async (e) => {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await startRegistration(form.name, form.email, form.password, form.password_confirmation);
      setStep('code');
      setCooldown(60);
    } catch (err) {
      setError(firstError(err, 'Could not send a verification code. Please try again.'));
    } finally {
      setBusy(false);
    }
  };

  const submitCode = async (e) => {
    e.preventDefault();
    setError(null);
    setBusy(true);
    try {
      await verifyRegistration(form.email, code);
      navigate('/connect');
    } catch (err) {
      setError(firstError(err, 'Could not verify that code. Please try again.'));
    } finally {
      setBusy(false);
    }
  };

  const resend = async () => {
    if (cooldown > 0) return;
    setError(null);
    setBusy(true);
    try {
      await resendRegistrationCode(form.email);
      setCooldown(60);
    } catch (err) {
      setError(firstError(err, 'Could not resend the code. Please try again.'));
      if (err.response?.status === 429 && err.response?.data?.retry_after_seconds) {
        setCooldown(err.response.data.retry_after_seconds);
      }
    } finally {
      setBusy(false);
    }
  };

  if (step === 'code') {
    return (
      <AuthShell
        title="Check your Gmail"
        subtitle={`Enter the code we sent to ${form.email}`}
      >
        <form onSubmit={submitCode} className="flex flex-col gap-4">
          <input
            type="text"
            inputMode="numeric"
            autoComplete="one-time-code"
            required
            placeholder="6-digit code"
            value={code}
            onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
            className={`${inputClass} text-center tracking-[0.5em] text-lg`}
            maxLength={8}
          />
          {error && <p className="text-sm text-apple">{error}</p>}
          <button
            disabled={busy || code.length === 0}
            className="bg-aurora text-[#0B0A12] rounded-xl py-3 font-medium hover:opacity-90 transition-opacity disabled:opacity-50"
          >
            {busy ? 'Verifying…' : 'Verify & create account'}
          </button>
        </form>
        <div className="flex items-center justify-between mt-6 text-sm text-mist">
          <button
            type="button"
            onClick={() => setStep('details')}
            className="underline underline-offset-4 hover:text-fg transition-colors"
          >
            Use a different email
          </button>
          <button
            type="button"
            onClick={resend}
            disabled={cooldown > 0 || busy}
            className="underline underline-offset-4 hover:text-fg transition-colors disabled:opacity-50 disabled:no-underline"
          >
            {cooldown > 0 ? `Resend code (${cooldown}s)` : 'Resend code'}
          </button>
        </div>
      </AuthShell>
    );
  }

  return (
    <AuthShell title="Create your account" subtitle="A Gmail address is required — we'll send a code to verify it">
      <form onSubmit={submitDetails} className="flex flex-col gap-4">
        <input
          type="text"
          required
          placeholder="Full name"
          value={form.name}
          onChange={(e) => setForm({ ...form, name: e.target.value })}
          className={inputClass}
        />
        <input
          type="email"
          required
          placeholder="you@gmail.com"
          value={form.email}
          onChange={(e) => setForm({ ...form, email: e.target.value })}
          className={inputClass}
        />
        <input
          type="password"
          required
          placeholder="Password (min 8 characters)"
          value={form.password}
          onChange={(e) => setForm({ ...form, password: e.target.value })}
          className={inputClass}
        />
        <input
          type="password"
          required
          placeholder="Confirm password"
          value={form.password_confirmation}
          onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })}
          className={inputClass}
        />
        {error && <p className="text-sm text-apple">{error}</p>}
        <button
          disabled={busy}
          className="bg-aurora text-[#0B0A12] rounded-xl py-3 font-medium hover:opacity-90 transition-opacity disabled:opacity-50"
        >
          {busy ? 'Sending code…' : 'Send verification code'}
        </button>
      </form>
      <p className="text-center text-sm text-mist mt-6">
        Already have an account?{' '}
        <Link to="/login" className="text-fg underline underline-offset-4">
          Log in
        </Link>
      </p>
    </AuthShell>
  );
}
