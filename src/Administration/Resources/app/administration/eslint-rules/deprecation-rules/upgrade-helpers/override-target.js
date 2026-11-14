/**
 * @sw-package framework
 *
 * Finds the core component a script overrides or extends, so a codemod can flag generic member names
 * like `isLoading` only where they belong to the deprecated component.
 *
 * The target comes from, in this order:
 * 1. `Component.override('<name>', {...})` or `Component.extend('<new>', '<name>', {...})` in the same file,
 *    also via `Shopware.Component`;
 * 2. the folder name, when it is a core component name (`extension/sw-order-list/index.js` with
 *    `export default {...}`), which covers `Component.override('sw-order-list', () => import('./…'))`.
 * Without either, the target is unknown and rules only check names that are unique in the core.
 */

const path = require('path');
const { walk, readComponentCall } = require('./component-tags');

const CORE_COMPONENT_NAME = /^sw-[a-z0-9-]+$/;
const cache = new WeakMap();

/**
 * The options object of a component definition, also inside `wrapComponentConfig({...})`, `defineComponent({...})`
 * and TypeScript casts.
 */
function unwrapOptions(node) {
    if (!node) {
        return null;
    }

    if (node.type === 'ObjectExpression') {
        return node;
    }

    if (['TSAsExpression', 'TSSatisfiesExpression', 'TSTypeAssertion'].includes(node.type)) {
        return unwrapOptions(node.expression);
    }

    if (node.type === 'CallExpression' && node.arguments.length === 1) {
        return unwrapOptions(node.arguments[0]);
    }

    return null;
}

function exportDefaultOptions(ast) {
    const declaration = ast.body.find((statement) => statement.type === 'ExportDefaultDeclaration');

    return declaration ? unwrapOptions(declaration.declaration) : null;
}

/**
 * The folder name of the linted file when it is a core component name.
 */
function folderTarget(context) {
    const filename = context.physicalFilename ?? context.filename ?? '';
    const folder = path.basename(path.dirname(filename));

    return CORE_COMPONENT_NAME.test(folder) ? folder : null;
}

/**
 * Every override or extension of a component in the linted file.
 */
function findOverrides(context) {
    const ast = context.sourceCode.ast;

    if (cache.has(ast)) {
        return cache.get(ast);
    }

    const overrides = [];

    walk(ast, (node) => {
        if (node.type !== 'CallExpression') {
            return;
        }

        const call = readComponentCall(node);

        if (call && call.target && (call.method === 'override' || call.method === 'extend')) {
            overrides.push({ target: call.target, options: unwrapOptions(call.options), node, fromFolder: false });
        }
    });

    if (overrides.length === 0) {
        const target = folderTarget(context);
        const options = target ? exportDefaultOptions(ast) : null;

        if (options) {
            overrides.push({ target, options, node: options, fromFolder: true });
        }
    }

    cache.set(ast, overrides);

    return overrides;
}

/**
 * Every component the linted file overrides or extends.
 */
function fileTargets(context) {
    return [...new Set(findOverrides(context).map(({ target }) => target))];
}

module.exports = {
    fileTargets,
};
