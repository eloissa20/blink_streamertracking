// Artist / member color identities used by LevelUpCard.
// ---------------------------------------------------------------------
// Each theme is plain color tokens (no logos or copyrighted artwork) so
// the popup can restyle itself entirely with CSS — gradients, borders,
// glow, progress bar, and particle color all read from here.

export const THEMES = {
  blackpink: {
    key: 'blackpink',
    label: 'BLACKPINK',
    kind: 'group',
    gradientFrom: '#0A0A0C',
    gradientTo: '#1A0A14',
    accent: '#FF2E93',
    accentSoft: 'rgba(255, 46, 147, 0.18)',
    border: 'rgba(255, 46, 147, 0.55)',
    glow: 'rgba(255, 46, 147, 0.45)',
    text: '#FFFFFF',
    subtext: 'rgba(255, 255, 255, 0.7)',
    particleColors: ['#FF2E93', '#FF7EC1', '#FFFFFF', '#0A0A0C'],
  },
  jennie: {
    key: 'jennie',
    label: 'Jennie',
    kind: 'solo',
    gradientFrom: '#05191C',
    gradientTo: '#062A30',
    accent: '#02D8E9',
    accentSoft: 'rgba(2, 216, 233, 0.18)',
    border: 'rgba(2, 216, 233, 0.55)',
    glow: 'rgba(2, 216, 233, 0.45)',
    text: '#EAFEFF',
    subtext: 'rgba(234, 254, 255, 0.72)',
    particleColors: ['#02D8E9', '#8FF3FA', '#FFFFFF', '#05191C'],
  },
  jisoo: {
    key: 'jisoo',
    label: 'Jisoo',
    kind: 'solo',
    gradientFrom: '#211B2E',
    gradientTo: '#2A2440',
    accent: '#C9A9E8',
    accentSoft: 'rgba(201, 169, 232, 0.2)',
    border: 'rgba(230, 224, 210, 0.5)',
    glow: 'rgba(201, 169, 232, 0.45)',
    text: '#FBFAF6',
    subtext: 'rgba(251, 250, 246, 0.72)',
    particleColors: ['#C9A9E8', '#E9E4F5', '#FBFAF6', '#211B2E'],
  },
  rose: {
    key: 'rose',
    label: 'Rosé',
    kind: 'solo',
    // Accent is pure white per the updated brief — background stays a
    // near-black charcoal (rather than true black) so white text, the
    // white glow, and the white progress bar all still read with enough
    // contrast instead of disappearing into the card.
    gradientFrom: '#17161A',
    gradientTo: '#232025',
    accent: '#FFFFFF',
    accentSoft: 'rgba(255, 255, 255, 0.16)',
    border: 'rgba(255, 255, 255, 0.55)',
    glow: 'rgba(255, 255, 255, 0.35)',
    text: '#FFFFFF',
    subtext: 'rgba(255, 255, 255, 0.72)',
    particleColors: ['#FFFFFF', '#E7E7EA', '#B9B9C0', '#17161A'],
  },
  lisa: {
    key: 'lisa',
    label: 'Lisa',
    kind: 'solo',
    gradientFrom: '#141312',
    gradientTo: '#241F0A',
    accent: '#F5D400',
    accentSoft: 'rgba(245, 212, 0, 0.18)',
    border: 'rgba(245, 212, 0, 0.55)',
    glow: 'rgba(245, 212, 0, 0.5)',
    text: '#FFFFFF',
    subtext: 'rgba(255, 255, 255, 0.72)',
    particleColors: ['#F5D400', '#FFFFFF', '#141312', '#FFEA70'],
  },
};

// Best-effort match from a free-text artist name (as it comes back from
// Spotify/Apple Music metadata) to one of the themes above. Falls back to
// the group theme so any unmatched BLACKPINK-related credit still looks
// intentional rather than unstyled.
export function themeForArtistName(name = '') {
  const n = name.toLowerCase();
  if (n.includes('jennie')) return THEMES.jennie;
  if (n.includes('jisoo')) return THEMES.jisoo;
  if (n.includes('rose') || n.includes('rosé')) return THEMES.rose;
  if (n.includes('lisa')) return THEMES.lisa;
  return THEMES.blackpink;
}
