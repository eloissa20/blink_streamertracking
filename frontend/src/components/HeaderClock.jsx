import { useEffect, useState } from 'react';
import { formatClock, timeAgo } from '../lib/time';

export default function HeaderClock({ lastSyncedAt }) {
  const [now, setNow] = useState(() => new Date());

  useEffect(() => {
    const id = setInterval(() => setNow(new Date()), 1000);
    return () => clearInterval(id);
  }, []);

  return (
    <div className="mt-1 flex flex-col gap-0.5 text-sm text-mist font-mono tabular">
      <span>{formatClock(now)}</span>
      <span>
        {lastSyncedAt
          ? `Last synced ${timeAgo(lastSyncedAt)}`
          : 'Not synced yet'}
      </span>
    </div>
  );
}
