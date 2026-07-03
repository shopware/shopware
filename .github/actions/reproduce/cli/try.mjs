// `repro try` — the agent's OPTIONAL, non-authoritative preview. Runs the bundle on the CURRENT live
// state (no DB reset, so it's fast to iterate) and writes builder-result.json for feedback only. It
// is NOT the mechanism of record: the official result comes from `verify` in the post step, running
// the same code on a fresh DB. The agent is told it doesn't need this — exploration + seed + check
// usually give enough confidence — but it's here as cheap insurance for the Playwright last mile.
import { FILES } from './lib.mjs';
import { pipeline } from './pipeline.mjs';

/**
 * Runs the bundle as an agent-facing preview without resetting the database.
 *
 * The result is useful repair feedback in `builder-result.json`, but the official verdict still
 * comes from `verify` on clean reported and trunk legs.
 */
export const tryBundle = () => pipeline({ target: 'builder', out: FILES.builderResult, reset: false });
