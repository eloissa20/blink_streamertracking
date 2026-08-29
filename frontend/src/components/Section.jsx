import { motion } from 'framer-motion';

export default function Section({ eyebrow, title, action, children, delay = 0 }) {
  return (
    <motion.section
      initial={{ opacity: 0, y: 20 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: '-60px' }}
      transition={{ duration: 0.55, delay, ease: [0.16, 1, 0.3, 1] }}
      className="glass-card p-4 sm:p-6 md:p-8"
    >
      <div className="flex items-center justify-between mb-6 flex-wrap gap-4">
        <div>
          {eyebrow && (
            <p
              className="text-xs uppercase tracking-[0.2em] font-semibold mb-1 transition-colors duration-500"
              style={{ color: 'var(--theme-accent-strong, #7FCBAE)' }}
            >
              {eyebrow}
            </p>
          )}
          <h2 className="font-display text-xl sm:text-2xl font-semibold text-fg">{title}</h2>
        </div>
        {action}
      </div>
      {children}
    </motion.section>
  );
}
