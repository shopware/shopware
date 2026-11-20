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
 * returns to the list when its part is deliberately postponed, as `block-contract` and `member-contract` are
 * until the contracts can be type-checked.
 *
 * `marker-checks` expects the break check to reject a marker that names a parameter its method does not have
 * and a `{@link}` to a member that does not exist. The break check does not validate markers yet.
 */
export const EXPECTED_FAILURES: ExpectedFailures = {
    views: [
        'block-contract',
        'member-contract',
        'complete',
    ],
    comparisons: ['marker-checks/member-findings'],
};
