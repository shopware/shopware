/**
 * @sw-package framework
 *
 * Finds the core component an `.html.twig` template overrides. A template alone does not name its component,
 * so the helper reads the script next to it:
 *
 * 1. `index.js`/`index.ts` in the same folder: the target of `Component.override('<name>'` or
 *    `Component.extend('<new>', '<name>'`; the file is only read, never written;
 * 2. otherwise the file name (`sw-order-list.html.twig`) or the folder name, when it is a core component name.
 */

const fs = require('fs');
const path = require('path');

const CORE_COMPONENT_NAME = /^sw-[a-z0-9-]+$/;
const CALL = /Component\s*\.\s*(override|extend)\(\s*(['"`])([\w-]+)\2\s*(?:,\s*(['"`])([\w-]+)\4)?/g;
const SCRIPT_NAMES = ['index.js', 'index.ts'];

function physicalFilename(context) {
    return context.physicalFilename ?? context.filename ?? '';
}

function readSiblingScripts(directory) {
    return SCRIPT_NAMES.flatMap((name) => {
        try {
            return [fs.readFileSync(path.join(directory, name), 'utf8')];
        } catch {
            return [];
        }
    });
}

/**
 * The core components the linted template may override.
 *
 * @param {import('eslint').Rule.RuleContext} context
 * @returns {string[]}
 */
function twigTargets(context) {
    const filename = physicalFilename(context);
    const directory = path.dirname(filename);
    const targets = new Set();

    for (const source of readSiblingScripts(directory)) {
        for (const match of source.matchAll(CALL)) {
            targets.add(match[1] === 'extend' && match[5] ? match[5] : match[3]);
        }
    }

    if (targets.size === 0) {
        const base = path.basename(filename).replace(/(\.html)?\.twig$/, '');

        [base, path.basename(directory)].filter((name) => CORE_COMPONENT_NAME.test(name)).forEach((name) => targets.add(name));
    }

    return [...targets];
}

/**
 * Whether a script in the same folder imports the linted template, so a script rule can handle it.
 *
 * @param {import('eslint').Rule.RuleContext} context
 * @returns {boolean}
 */
function isImportedBySiblingScript(context) {
    const filename = physicalFilename(context);
    const base = path.basename(filename);

    return readSiblingScripts(path.dirname(filename)).some((source) => source.includes(`./${base}`));
}

module.exports = {
    twigTargets,
    isImportedBySiblingScript,
};
