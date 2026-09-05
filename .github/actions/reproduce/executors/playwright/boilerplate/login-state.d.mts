// Type declarations for the harness-owned admin login helper (a plain .mjs script excluded from the
// TypeScript program). Only the Playwright auth-preparer and readiness-check import it.
export function ensureAdminState(
  appUrl: string,
  options?: { out?: string; force?: boolean },
): boolean;
export function loginToState(appUrl: string, out: string): Promise<void>;
