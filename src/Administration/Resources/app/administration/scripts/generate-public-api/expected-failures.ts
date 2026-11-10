/**
 * @sw-package framework
 */

/**
 * @private
 *
 * Fixture comparisons that fail because their part of the generator or the break check does not exist yet.
 * `fixtures.spec.ts` runs them with `it.failing`, so an entry that starts to pass fails the run until it is
 * removed here. The list only shrinks: a new fixture case has to pass, or come with the code that makes it pass.
 */
export const EXPECTED_FAILURES: readonly string[] = [
    'attributes-inside-block/blocks',
    'attributes-inside-block/complete',
    'block-context/blocks',
    'block-context/complete',
    'block-moved/blocks',
    'block-moved/complete',
    'block-renamed-major-only/blocks',
    'block-renamed-major-only/complete',
    'element-crosses-block/blocks',
    'element-crosses-block/complete',
];
