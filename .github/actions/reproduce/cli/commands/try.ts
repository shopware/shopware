/**
 * Preview command for running the bundle on current live state without resetting the database.
 *
 * This is agent feedback only. The official result still comes from `verify`, which runs the same
 * pipeline on clean reported and trunk legs.
 */
import { FILES } from '../../bundle.ts';
import { fullRun } from '../full-run.ts';

/**
 * Runs the bundle as an agent-facing preview without resetting the database.
 *
 * The result is useful repair feedback in `builder-result.json`, but the official verdict still
 * comes from `verify` on clean reported and trunk legs.
 */
export const tryBundle = () => fullRun({ target: 'builder', out: FILES.builderResult, reset: false });
