/** Server clock minus client clock, measured when a response arrives. */
export function clockOffset(serverTime: string, nowMs: number): number {
  return Date.parse(serverTime) - nowMs;
}

export function remainingSeconds(
  expiresAt: string,
  offsetMs: number,
  nowMs: number,
): number {
  const ms = Date.parse(expiresAt) - (nowMs + offsetMs);
  return Math.max(0, Math.ceil(ms / 1000));
}

export function formatMmSs(totalSeconds: number): string {
  const m = Math.floor(totalSeconds / 60);
  const s = totalSeconds % 60;
  return `${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`;
}
