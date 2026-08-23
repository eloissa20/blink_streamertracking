import LevelUpCard from './LevelUpCard';
import useStreamerLevelUps from '../hooks/useStreamerLevelUps';

/**
 * Drop this once anywhere in the authenticated part of the tree. It pulls
 * newly-unlocked levels from the backend (GET /me/streamer-levels — see
 * useStreamerLevelUps) and renders one LevelUpCard at a time.
 */
export default function LevelUpQueue() {
  const { current, dismissCurrent } = useStreamerLevelUps();

  if (!current) return null;

  return (
    <LevelUpCard
      type={current.type}
      theme={current.theme}
      level={current.level}
      tier={current.tier}
      totalStreams={current.totalStreams}
      progress={current.progress}
      artistName={current.artistName}
      memberName={current.memberName}
      songTitle={current.songTitle}
      imageUrl={current.imageUrl}
      onClose={dismissCurrent}
    />
  );
}
