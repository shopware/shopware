/**
 * @sw-package framework
 *
 * Finds out which Administration component a piece of extension code belongs to, so the deprecation
 * rules only report members of the component whose API changed.
 */

const path = require('path');

const COMPONENT_FACTORY_METHODS = new Set(['register', 'override', 'extend']);

/**
 * The dotted path of a member chain like `Shopware.Component`, or null for computed members and calls.
 *
 * @param {import('eslint').Rule.Node} node
 * @returns {string | null}
 */
function dottedPath(node) {
    if (node.type === 'Identifier') {
        return node.name;
    }

    if (node.type === 'ThisExpression') {
        return 'this';
    }

    if (node.type === 'MemberExpression' && !node.computed && node.property.type === 'Identifier') {
        const objectPath = dottedPath(node.object);

        return objectPath === null ? null : `${objectPath}.${node.property.name}`;
    }

    return null;
}

/**
 * @param {import('eslint').Rule.Node | null | undefined} node
 * @returns {string | null}
 */
function staticString(node) {
    if (node?.type === 'Literal' && typeof node.value === 'string') {
        return node.value;
    }

    if (node?.type === 'TemplateLiteral' && node.expressions.length === 0) {
        return node.quasis[0].value.cooked;
    }

    return null;
}

/**
 * The static name of a member access or object property.
 *
 * @param {import('eslint').Rule.Node} node
 * @returns {string | null}
 */
function propertyName(node) {
    if (node.type === 'MemberExpression') {
        return node.computed ? staticString(node.property) : node.property.name;
    }

    if (node.type === 'Property' || node.type === 'MethodDefinition') {
        return node.computed ? staticString(node.key) : (node.key.name ?? staticString(node.key));
    }

    return null;
}

/**
 * For `Component.override('sw-x', ...)` and `Component.extend('new', 'sw-x', ...)` the name of the
 * component that is changed or extended, for `Component.register('sw-x', ...)` the registered name.
 *
 * @param {import('eslint').Rule.Node} node
 * @returns {{ method: string, name: string } | null}
 */
function componentFactoryCall(node) {
    if (node.type !== 'CallExpression' || node.callee.type !== 'MemberExpression' || node.callee.computed) {
        return null;
    }

    const method = node.callee.property.name;
    const objectPath = dottedPath(node.callee.object);

    if (!COMPONENT_FACTORY_METHODS.has(method) || !['Component', 'Shopware.Component'].includes(objectPath)) {
        return null;
    }

    const name = staticString(method === 'extend' ? node.arguments[1] : node.arguments[0]);

    return name === null ? null : { method, name };
}

/**
 * The components a spot belongs to: the enclosing `Component.register/override/extend()` call,
 * otherwise the folder and base name of the file, the convention for override files such as
 * `sw-order-list/index.js`, `sw-order-list/sw-order-list.html.twig` and `sw-order-list.override.vue`.
 *
 * @param {import('eslint').Rule.RuleContext} context
 * @param {import('eslint').Rule.Node | null} node
 * @returns {string[]}
 */
function componentContext(context, node) {
    for (let current = node; current; current = current.parent) {
        const call = current.type === 'CallExpression' ? componentFactoryCall(current) : null;

        if (call) {
            return [call.name];
        }
    }

    const filename = context.physicalFilename ?? context.filename;
    const folder = path.basename(path.dirname(filename));
    const base = path.basename(filename).replace(/(\.override)?\.(html\.twig|vue|[cm]?[jt]sx?)$/, '');

    return [folder, base];
}

/**
 * @param {import('eslint').Rule.RuleContext} context
 * @param {import('eslint').Rule.Node | null} node
 * @param {string[]} components
 */
function isInComponents(context, node, components) {
    return componentContext(context, node).some((name) => components.includes(name));
}

/**
 * The options object of the component around `node`: the object passed to
 * `Component.register/override/extend()`, `wrapComponentConfig()` or `defineComponent()`,
 * or the object a file exports by default.
 *
 * @param {import('eslint').Rule.Node} node
 * @returns {import('eslint').Rule.Node | null}
 */
function componentOptions(node) {
    for (let current = node; current?.parent; current = current.parent) {
        if (current.type !== 'ObjectExpression') {
            continue;
        }

        const parent = current.parent;

        if (parent.type === 'ExportDefaultDeclaration') {
            return current;
        }

        if (parent.type !== 'CallExpression' || !parent.arguments.includes(current)) {
            continue;
        }

        const calleeName = parent.callee.type === 'MemberExpression' ? parent.callee.property.name : parent.callee.name;

        if (componentFactoryCall(parent) || ['wrapComponentConfig', 'defineComponent'].includes(calleeName)) {
            return current;
        }
    }

    return null;
}

/**
 * Whether the options object defines `name` in one of its sections, e.g. its own `computed.dateFilter`.
 *
 * @param {import('eslint').Rule.Node | null} options
 * @param {string[]} sections
 * @param {string} name
 */
function definesMember(options, sections, name) {
    if (!options) {
        return false;
    }

    return options.properties.some((section) => {
        if (section.type !== 'Property' || !sections.includes(propertyName(section))) {
            return false;
        }

        return section.value.type === 'ObjectExpression'
            && section.value.properties.some((member) => member.type === 'Property' && propertyName(member) === name);
    });
}

/**
 * Whether `node` is `this.$super('<name>', ...)`.
 *
 * @param {import('eslint').Rule.Node} node
 * @param {string[]} names
 */
function isSuperCallOf(node, names) {
    return node.type === 'CallExpression'
        && node.callee.type === 'MemberExpression'
        && node.callee.object.type === 'ThisExpression'
        && propertyName(node.callee) === '$super'
        && names.includes(staticString(node.arguments[0]));
}

/**
 * Whether `node` is the service: `this.<name>`, `<name>`, `Shopware.Service('<name>')` or `Service('<name>')`.
 *
 * @param {import('eslint').Rule.Node} node
 * @param {string} name
 */
function isService(node, name) {
    if (node.type === 'Identifier') {
        return node.name === name;
    }

    if (node.type === 'MemberExpression') {
        return propertyName(node) === name;
    }

    return node.type === 'CallExpression'
        && ['Shopware.Service', 'Service'].includes(dottedPath(node.callee))
        && staticString(node.arguments[0]) === name;
}

/**
 * Whether `node` is the Pinia store `Shopware.Store.get('<id>')` or `Store.get('<id>')`.
 *
 * @param {import('eslint').Rule.Node} node
 * @param {string} id
 */
function isStore(node, id) {
    return node.type === 'CallExpression'
        && ['Shopware.Store.get', 'Store.get'].includes(dottedPath(node.callee))
        && staticString(node.arguments[0]) === id;
}

module.exports = {
    isService,
    isStore,
    dottedPath,
    staticString,
    propertyName,
    componentFactoryCall,
    componentContext,
    isInComponents,
    componentOptions,
    definesMember,
    isSuperCallOf,
};
