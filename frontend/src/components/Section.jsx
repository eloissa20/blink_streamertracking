import { motion } from 'framer-motion';

export default function Section({ eyebrow, title, action, children }) {
  return (
    <motion.section
      initial={{ opacity: 0, y: 20 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: '-60px' }}
      transition={{ duration: 0.55, ease: [0.16, 1, 0.3, 1] }}
      className="glass-card rounded-3xl p-6 md:p-8"
    >
      <div className="flex items-center justify-between mb-6 flex-wrap gap-4">
        <div>
          {eyebrow && (
            <p className="text-xs uppercase tracking-[0.2em] text-violet-bright font-semibold mb-1">
              {eyebrow}
            </p>
          )}
          <h2 className="font-display text-2xl font-semibold text-white">{title}</h2>
        </div>
        {action}
      </div>
      {children}
    </motion.section>
  );
}
