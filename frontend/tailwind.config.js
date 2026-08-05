/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{js,jsx}'],
  theme: {
    extend: {
      colors: {
        ink: {
          DEFAULT: '#0B0A12',
          soft: '#121120',
          surface: '#181729',
          border: '#2A2842',
        },
        violet: {
          DEFAULT: '#6C5CE7',
          bright: '#8B7CFF',
        },
        teal: {
          DEFAULT: '#00D9C0',
        },
        spotify: '#1ED760',
        apple: '#FA2D48',
        mist: '#9C9AB8',
      },
      fontFamily: {
        display: ['"Space Grotesk"', 'sans-serif'],
        body: ['"Inter"', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'monospace'],
      },
      backgroundImage: {
        aurora: 'linear-gradient(120deg, #6C5CE7 0%, #00D9C0 100%)',
        'aurora-soft': 'linear-gradient(120deg, rgba(108,92,231,0.15) 0%, rgba(0,217,192,0.15) 100%)',
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
