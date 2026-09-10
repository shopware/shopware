/**
 * @sw-package framework
 *
 * The files Jest loads for the `shopware:*` specifiers.
 *
 * Jest resolves a request to a file on disk, and there are 90-odd specifiers, so a one-line stub is
 * written per specifier rather than checked in. They are all created once from `jest.config.ts`, in the
 * main process: creating them from the resolver instead would have every worker racing to write the same
 * file on a cold cache.
 */

const fs = require('fs');
const path = require('path');

const RESOLVER = path.join(__dirname, 'resolve-module.js');
const STUB_DIR = path.join(__dirname, '..', '..', 'node_modules', '.cache', 'shopware-virtual-modules');

/** Absolute path of the stub for one specifier, whether or not it exists yet. */
function stubPath(specifier) {
    return path.join(STUB_DIR, `${specifier.replace(/[:/]/g, '__')}.js`);
}

function stubContents(specifier) {
    return `module.exports = require(${JSON.stringify(RESOLVER)})(${JSON.stringify(specifier)});\n`;
}

/** Writes a stub per specifier the registry publishes, barrels and subpaths alike. */
function writeStubs(registry) {
    const specifiers = Object.entries(registry).flatMap(
        ([
            family,
            entry,
        ]) => [
            ...(entry.exports.length > 0 ? [family] : []),
            ...Object.keys(entry.subpaths).map((key) => `${family}/${key}`),
        ],
    );

    fs.mkdirSync(STUB_DIR, { recursive: true });
    specifiers.forEach((specifier) => fs.writeFileSync(stubPath(specifier), stubContents(specifier)));

    return specifiers.length;
}

module.exports = { stubPath, writeStubs };
