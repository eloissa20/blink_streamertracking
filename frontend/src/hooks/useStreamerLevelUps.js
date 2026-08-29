import { useCallback, useEffect, useState } from 'react';
import api from '../api/client';
import { themeForArtistName } from '../lib/artistThemes';

/**
 * Achievements now live on the backend (see StreamerLevelController /
 * streamer_achievements table) instead of the browser's localStorage, so
 * a level unlocked on one device still shows up — and doesn't re-pop —
 * on another.
 *
 * GET /me/streamer-levels recomputes the user's real play counts from
 * play_records, persists any newly-crossed levels, and hands back just
 * the ones that were newly unlocked by *this* call. We queue those and
 * show one LevelUpCard at a time.
 */
export default function useStreamerLevelUps() {
  const [queue, setQueue] = useState([]);
  const [achievements, setAchievements] = useState([]);
  const [loading, setLoading] = useState(true);
  // Every `key:level` unlocked by the most recent refresh — kept around
  // (unlike `queue`, which drains as popups are dismissed) so the
  // Achievements grid can still highlight the matching badge with a
  // pulse/glow after the celebration popup itself has been closed.
  const [newlyUnlockedKeys, setNewlyUnlockedKeys] = useState(() => new Set());

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const { data } = await api.get('/me/streamer-levels');
      setAchievements(data.achievements ?? []);

      const newCards = (data.newly_unlocked ?? []).map((a) => ({
        id: `${a.key}:${a.level}`,
        type: a.type,
        theme: themeForArtistName(a.member_name ?? a.artist_name ?? ''),
        level: a.level,
        tier: a.tier,
        totalStreams: a.total_streams,
        artistName: a.artist_name,
        memberName: a.member_name,
        songTitle: a.song_title,
        imageUrl: a.image_url,
      }));

      if (newCards.length) {
        setQueue((prev) => [...prev, ...newCards]);
        setNewlyUnlockedKeys((prev) => {
          const next = new Set(prev);
          newCards.forEach((c) => next.add(c.id));
          return next;
        });
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    refresh();
  }, [refresh]);

  const dismissCurrent = useCallback(() => {
    setQueue((prev) => prev.slice(1));
  }, []);

  return {
    current: queue[0] ?? null,
    remaining: queue.length,
    dismissCurrent,
    achievements,
    newlyUnlockedKeys,
    loading,
    refresh,
  };
}
