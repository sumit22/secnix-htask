export interface Env {
  readonly apiUrl: string;
}

export function readEnv(
  raw: string | undefined = process.env.NEXT_PUBLIC_API_URL,
): Env {
  if (!raw) {
    throw new Error("NEXT_PUBLIC_API_URL is required");
  }
  try {
    new URL(raw);
  } catch {
    throw new Error(`NEXT_PUBLIC_API_URL is not a valid URL: ${raw}`);
  }
  return { apiUrl: raw.replace(/\/+$/, "") };
}
