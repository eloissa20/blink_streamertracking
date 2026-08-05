import { motion } from 'framer-motion';
import Waveform from './Waveform';

export default function AuthShell({ title, subtitle, children }) {
  return (
    <div className="min-h-[80vh] flex items-center justify-center px-6">
      <motion.div
        initial={{ opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.5 }}
        className="w-full max-w-md glass-card rounded-3xl p-8"
      >
        <div className="flex justify-center mb-5">
          <Waveform bars={4} />
        </div>
        <h1 className="font-display text-2xl font-semibold text-center text-white mb-1">
          {title}
        </h1>
        <p className="text-center text-mist text-sm mb-8">{subtitle}</p>
        {children}
      </motion.div>
    </div>
  );
}
