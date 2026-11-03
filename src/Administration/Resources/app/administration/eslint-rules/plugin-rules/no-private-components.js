/**
 * @sw-package framework
 */

const fs = require('fs');
const path = require('path');

/** The checked-in registry of the installed Administration, which lists every published component module. */
const REGISTRY_FILE = path.resolve(__dirname, '../../shopware-modules.json');

/**
 * The names of the components the Administration publishes as `shopware:components/<name>`, its public API.
 *
 * @returns {Set<string>}
 */
function readPublicComponentNames() {
    const registry = JSON.parse(fs.readFileSync(REGISTRY_FILE, 'utf8'));

    return new Set(Object.keys(registry['shopware:components']?.subpaths ?? {}));
}

/** Which argument of each call names the Administration component it relies on. */
const NAME_ARGUMENT = {
    override: 0,
    build: 0,
    // `extend('my-component', 'sw-base', …)` relies on the component it extends.
    extend: 1,
};

/**
 * The method of `Shopware.Component.<method>(…)` or `Component.<method>(…)`, or null for any other call.
 *
 * @param {import('estree').CallExpression} node
 * @returns {string|null}
 */
function getComponentMethod(node) {
    const { callee } = node;
    if (callee.type !== 'MemberExpression' || callee.computed || !Object.hasOwn(NAME_ARGUMENT, callee.property.name)) {
        return null;
    }

    const { object } = callee;
    const isComponent =
        (object.type === 'Identifier' && object.name === 'Component') ||
        (object.type === 'MemberExpression' &&
            !object.computed &&
            object.object.type === 'Identifier' &&
            object.object.name === 'Shopware' &&
            object.property.name === 'Component');

    return isComponent ? callee.property.name : null;
}

module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description:
                'Warn when an extension overrides, extends or builds an Administration component that is not public API',
        },
        messages: {
            notPublic:
                "'{{name}}' is not public API of the Administration: an update can change or remove it without deprecation.",
        },
        schema: [],
    },
    create(context) {
        let publicNames = null;

        return {
            CallExpression(node) {
                const method = getComponentMethod(node);
                const argument = method === null ? undefined : node.arguments[NAME_ARGUMENT[method]];
                if (argument?.type !== 'Literal' || typeof argument.value !== 'string') {
                    return;
                }

                publicNames ??= readPublicComponentNames();
                if (!publicNames.has(argument.value)) {
                    context.report({ node: argument, messageId: 'notPublic', data: { name: argument.value } });
                }
            },
        };
    },
};
