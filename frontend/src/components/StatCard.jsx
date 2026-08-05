import { motion } from 'framer-motion';
import AnimatedCounter from './AnimatedCounter';

export default function StatCard({ label, value, delay = 0, accent = false }) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 16 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ duration: 0.5, delay, ease: [0.16, 1, 0.3, 1] }}
      className={`glass-card rounded-2xl p-6 relative overflow-hidden ${accent ? 'ring-1 ring-violet/40' : ''}`}
    >
      {accent && (
        <div className="absolute inset-0 bg-aurora-soft pointer-events-none" />
      )}
      <p className="relative text-xs uppercase tracking-[0.2em] text-mist font-medium mb-3">
        {label}
      </p>
      <p className="relative font-mono tabular text-3xl md:text-4xl font-semibold text-white">
        <AnimatedCounter value={value} />
      </p>
    </motion.div>
  );
}
