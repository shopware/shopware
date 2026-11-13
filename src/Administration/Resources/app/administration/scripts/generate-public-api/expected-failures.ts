/**
 * @sw-package framework
 */
import type { ExpectedFailures } from './fixture-runner';

/**
 * @private
 *
 * Fixture comparisons that fail because their part of the generator or the break check does not exist yet.
 * `fixtures.spec.ts` runs them with `it.failing`, so a comparison that starts to pass fails the run until it
 * leaves this list. A new fixture case has to pass, or come with the code that makes it pass. A view only
 * returns to the list when its part is deliberately postponed, as `block-contract` is until the contracts can
 * be type-checked.
 */
export const EXPECTED_FAILURES: ExpectedFailures = {
    views: [
        'block-contract',
        'block-structure',
        'complete',
    ],
    comparisons: [
        'block-context/block-findings',
        'block-moved/block-findings',
        'block-renamed-major-only/block-findings',
        'element-crosses-block/block-findings',
    ],
};
