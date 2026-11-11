/**
 * @sw-package framework
 */
import type { ExpectedFailures } from './fixture-runner';

/**
 * @private
 *
 * Fixture comparisons that fail because their part of the generator or the break check does not exist yet.
 * `fixtures.spec.ts` runs them with `it.failing`, so a comparison that starts to pass fails the run until it
 * leaves this list. The list only shrinks: a new fixture case has to pass, or come with the code that makes
 * it pass.
 */
export const EXPECTED_FAILURES: ExpectedFailures = {
    views: [
        'blocks',
        'complete',
    ],
    comparisons: [],
};
