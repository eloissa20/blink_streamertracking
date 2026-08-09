import { motion } from 'framer-motion';
import AnimatedCounter from './AnimatedCounter';

export default function StatCard({ label, value, delay = 0, accent = false }) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.5, delay, ease: [0.16, 1, 0.3, 1] }}
      className="glass-card p-4 sm:p-6 relative overflow-hidden transition-shadow duration-500"
      style={accent ? { boxShadow: '0 0 0 1px var(--theme-accent-border, rgba(89,178,146,0.4))' } : undefined}
    >
      {accent && (
        <div
          className="absolute inset-0 pointer-events-none transition-[background] duration-500"
          style={{
            background:
              'linear-gradient(120deg, var(--theme-accent-soft, rgba(89,178,146,0.15)) 0%, var(--theme-accent-soft-2, rgba(255,201,77,0.15)) 100%)',
          }}
        />
      )}
      <p className="relative text-[10px] sm:text-xs uppercase tracking-[0.15em] sm:tracking-[0.2em] text-mist font-medium mb-2 sm:mb-3">
        {label}
      </p>
      <p className="relative font-mono tabular text-2xl sm:text-3xl md:text-4xl font-semibold text-white">
        <AnimatedCounter value={value} />
      </p>
    </motion.div>
  );
}
