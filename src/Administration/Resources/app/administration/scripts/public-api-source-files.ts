/**
 * @package admin
 * @private
 */

// Fixture components under `_mocks_`/`__fixtures__` and inside split `*.spec/` directories are test
// scaffolding, not shipped public API. Without this the first fixture declaring an extension point
// silently becomes tracked API that the guard then defends forever.
const FIXTURE_PATH_REGEX = /(\/(?:_mocks_|__fixtures__)\/|\.spec\/)/;

/**
 * Builds the file filter that a `scripts/generate-*` list generator and its `src/meta/meta.spec.js`
 * guard share, so the two cannot disagree about which files carry public API.
 */
export function createSourceFileFilter(sourceFileRegex: RegExp): (filePath: string) => boolean {
    return (filePath) => sourceFileRegex.test(filePath) && !FIXTURE_PATH_REGEX.test(filePath);
}
