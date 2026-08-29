/** @type {import('tailwindcss').Config} */
export default {
  darkMode: ['selector', ':not(.light)'],
  content: ['./index.html', './src/**/*.{js,jsx}'],
  theme: {
    extend: {
      colors: {
        // ink, mist, and fg are driven by CSS custom properties (see
        // src/index.css) so the whole app repaints between day/night mode
        // just by toggling a `.light` class on <html> — no per-component
        // dark: variants needed. The rgb(... / <alpha-value>) format keeps
        // Tailwind's opacity modifiers (e.g. bg-ink/70) working.
        ink: {
          DEFAULT: 'rgb(var(--color-ink) / <alpha-value>)',
          soft: 'rgb(var(--color-ink-soft) / <alpha-value>)',
          surface: 'rgb(var(--color-ink-surface) / <alpha-value>)',
          border: 'rgb(var(--color-ink-border) / <alpha-value>)',
        },
        mist: 'rgb(var(--color-mist) / <alpha-value>)',
        fg: 'rgb(var(--color-fg) / <alpha-value>)',
        violet: {
          DEFAULT: '#59B292',
          bright: '#7FCBAE',
        },
        teal: {
          DEFAULT: '#FFC94D',
        },
        cream: '#FAE7CB',
        coral: '#FA6781',
        spotify: '#1ED760',
        apple: '#FA2D48',
      },
      fontFamily: {
        display: ['"Inter"', 'sans-serif'],
        body: ['"Inter"', 'sans-serif'],
        mono: ['"Inter"', 'sans-serif'],
      },
      backgroundImage: {
        aurora: 'linear-gradient(120deg, #59B292 0%, #FFC94D 100%)',
        'aurora-soft': 'linear-gradient(120deg, rgba(89,178,146,0.15) 0%, rgba(255,201,77,0.15) 100%)',
      },
      keyframes: {
        rise: {
          '0%': { opacity: 0, transform: 'translateY(14px)' },
          '100%': { opacity: 1, transform: 'translateY(0)' },
        },
        wave: {
          '0%, 100%': { transform: 'scaleY(0.3)' },
          '50%': { transform: 'scaleY(1)' },
        },
        glow: {
          '0%, 100%': { opacity: 0.6 },
          '50%': { opacity: 1 },
        },
      },
      animation: {
        rise: 'rise 0.6s cubic-bezier(0.16, 1, 0.3, 1) both',
        wave: 'wave 1s ease-in-out infinite',
        glow: 'glow 3s ease-in-out infinite',
      },
    },
  },
  plugins: [],
};
