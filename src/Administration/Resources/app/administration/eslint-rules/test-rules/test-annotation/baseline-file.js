/**
 * @sw-package framework
 */

const { existsSync, readFileSync, statSync } = require('node:fs');
const { dirname, relative, sep } = require('node:path');

const cache = new Map();

/**
 * Reads the baseline of tests that predate the annotation rule: `{ "<spec path>": { "<identity>": <count> } }`.
 * Paths are relative to the baseline file. A missing file is an empty baseline.
 */
function readBaseline(path) {
    if (!path || !existsSync(path)) {
        return {};
    }

    const modified = statSync(path).mtimeMs;
    const cached = cache.get(path);

    if (cached?.modified === modified) {
        return cached.baseline;
    }

    const baseline = JSON.parse(readFileSync(path, 'utf8'));
    cache.set(path, { modified, baseline });

    return baseline;
}

/**
 * The key a spec file has in the baseline.
 *
 * @example
 * baselineKey('/admin/test-annotation-baseline.json', '/admin/src/a.spec.js'); // 'src/a.spec.js'
 */
function baselineKey(baselinePath, filename) {
    return relative(dirname(baselinePath), filename).split(sep).join('/');
}

module.exports = {
    readBaseline,
    baselineKey,
};
