// `repro verify` — the TRUSTED, deterministic leg. Resets the DB to the clean snapshot, seeds, and
// runs the bundle in one fixed sequence, writing the OFFICIAL result.json. It is NOT agent-facing:
// it is invoked only by the guarded post-step (reported leg, from an immutable copy of this CLI) and
// the trunk job (fresh runner). The REPRO_ALLOW_VERIFY gate ensures the agent — whose shell can run
// `repro …` — cannot produce the authoritative result itself, so it can never fake a verdict.
import { FILES, die } from './lib.mjs';
import { pipeline } from './pipeline.mjs';

export function verify() {
  if (process.env.REPRO_ALLOW_VERIFY !== '1') {
    die('verify is reserved for the deterministic post-step; the agent does not run it (use `try` for a preview)');
  }
  return pipeline({ target: process.env.TARGET || 'reported', out: FILES.result, reset: true });
}
