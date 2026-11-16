/**
 * @sw-package framework
 *
 * Finds the component a template belongs to. An `.html.twig` template does not name its component, so the scripts
 * next to it are read (`index.js`, but also `sw-order-list.js` and the like, specs excluded); a `.vue` file names it
 * in its own script. The files are only read, never written.
 *
 * - The targets are the core components of `Component.override('<name>'` and `Component.extend('<new>', '<name>'`
 *   in those scripts, otherwise the file name (`sw-order-list.html.twig`) or the folder name when it is a core
 *   component name.
 * - The template belongs to a component the extension registers itself when those scripts register a component and
 *   override or extend none, or when a script of the folder above registers it lazily under the template's file or
 *   folder name, as in `Component.register('sw-cms-el-config-acme', () => import('./config'))`.
 */

const fs = require('fs');
const path = require('path');

const CORE_COMPONENT_NAME = /^sw-[a-z0-9-]+$/;
const CALL = /Component\s*\.\s*(override|extend)\(\s*(['"`])([\w-]+)\2\s*(?:,\s*(['"`])([\w-]+)\4)?/g;
const REGISTER = /Component\s*\.\s*register\(\s*(['"`])([\w-]+)\1/g;
const SCRIPT = /\.(?:[cm]?js|[cm]?ts)$/;
const SPEC = /\.spec\.[cm]?[jt]s$/;

/**
 * The sources of the scripts in a folder, specs excluded.
 */
function readScripts(directory) {
    let names;

    try {
        names = fs.readdirSync(directory);
    } catch {
        return '';
    }

    return names
        .filter((name) => SCRIPT.test(name) && !SPEC.test(name))
        .sort()
        .flatMap((name) => {
            try {
                return [fs.readFileSync(path.join(directory, name), 'utf8')];
            } catch {
                return [];
            }
        })
        .join('\n');
}

/**
 * The core components the linted template may override, and whether it belongs to a component the extension
 * registers itself.
 *
 * @example
 * getTemplateOwner(context); // { targets: ['sw-order-general-info'], ownComponent: false }
 */
function getTemplateOwner(context) {
    const filename = context.physicalFilename ?? context.filename ?? '';
    const directory = path.dirname(filename);
    const scripts = filename.endsWith('.vue') ? context.sourceCode.text : readScripts(directory);
    const names = [path.basename(filename).replace(/(\.html)?\.twig$|\.vue$/, ''), path.basename(directory)];
    const targets = new Set();

    for (const match of scripts.matchAll(CALL)) {
        targets.add(match[1] === 'extend' && match[5] ? match[5] : match[3]);
    }

    if (targets.size === 0) {
        names.filter((name) => CORE_COMPONENT_NAME.test(name)).forEach((name) => targets.add(name));
    }

    const ownComponent =
        !/Component\s*\.\s*(override|extend)\(/.test(scripts) &&
        (/Component\s*\.\s*register\(/.test(scripts) ||
            [...readScripts(path.dirname(directory)).matchAll(REGISTER)].some((match) => names.includes(match[2])));

    return { targets: [...targets], ownComponent };
}

module.exports = {
    getTemplateOwner,
};
