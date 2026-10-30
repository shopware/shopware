/**
 * @sw-package framework
 * @private
 */

import { isAbsolute, relative, resolve } from 'node:path';

/** The `src/` folder that holds the platform's bundles. */
const platformSourcePath = resolve(__dirname, '../..', '../../../..');

/**
 * Whether the current spec belongs to the platform's own Jest run, where an unasserted deprecation fails the test.
 * Extensions that load this test setup keep their specs outside the platform's `src/`, for example in
 * `custom/plugins/`. Outside a test, it counts as the platform's run.
 * Remove it once core's deprecation baseline is stable, see
 * `adr/2026-10-23-fail-on-administration-deprecations-only-in-core-tests.md`.
 *
 * @private
 * @example
 * if (failing.length === 0 || !isPlatformDeprecationRun()) {
 *     return;
 * }
 */
export function isPlatformDeprecationRun(): boolean {
    const { testPath } = expect.getState();

    if (testPath === undefined) {
        return true;
    }

    const pathInPlatformSource = relative(platformSourcePath, testPath);

    return !pathInPlatformSource.startsWith('..') && !isAbsolute(pathInPlatformSource);
}
