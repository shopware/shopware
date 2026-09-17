/**
 * @sw-package framework
 *
 * The files Jest loads for the `shopware:*` specifiers.
 *
 * Jest resolves a request to a file on disk, and there are 90-odd specifiers, so a one-line stub is
 * written per specifier rather than checked in. They are all created once from `jest.config.ts`, in the
 * main process: creating them from the resolver instead would have every worker racing to write the same
 * file on a cold cache.
 *
 * Keep this plain JavaScript with no local imports: `jest-resolver.js` loads it from inside jest-resolve,
 * which runs outside the Jest runtime and cannot require a TypeScript file.
 */

const fs = require('fs');
const path = require('path');

const RESOLVER = path.join(__dirname, 'resolve-module.js');
const STUB_DIR = path.join(__dirname, '..', '..', 'node_modules', '.cache', 'shopware-virtual-modules');

/** Absolute path of the stub for one specifier, whether or not it exists yet. */
function stubPath(specifier, stubDirectory = STUB_DIR) {
    return path.join(stubDirectory, `${specifier.replace(/[:/]/g, '__')}.js`);
}

function stubContents(specifier) {
    return `module.exports = require(${JSON.stringify(RESOLVER)})(${JSON.stringify(specifier)});\n`;
}

/** Returns the current stub for a specifier, or `undefined` when the registry does not publish it. */
function resolveStub(specifier, stubDirectory = STUB_DIR) {
    const file = stubPath(specifier, stubDirectory);

    return fs.existsSync(file) ? file : undefined;
}

/** Replaces the generated stubs with the specifiers from the current registry. */
function writeStubs(specifiers, stubDirectory = STUB_DIR) {
    fs.mkdirSync(stubDirectory, { recursive: true });

    const currentFiles = new Set(specifiers.map((specifier) => stubPath(specifier, stubDirectory)));
    fs.readdirSync(stubDirectory).forEach((entry) => {
        const file = path.join(stubDirectory, entry);

        if (!currentFiles.has(file) && fs.statSync(file).isFile()) {
            fs.unlinkSync(file);
        }
    });

    specifiers.forEach((specifier) => fs.writeFileSync(stubPath(specifier, stubDirectory), stubContents(specifier)));

    return specifiers.length;
}

module.exports = { resolveStub, stubPath, writeStubs };
