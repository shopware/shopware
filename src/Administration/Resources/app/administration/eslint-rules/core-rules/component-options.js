/**
 * @sw-package framework
 */

// An object literal with one of these keys is a component, a module or store definition has none of them.
const COMPONENT_OPTION_KEYS = new Set([
    'template',
    'render',
    'setup',
    'props',
    'emits',
    'data',
    'computed',
    'methods',
    'watch',
    'inject',
    'provide',
    'mixins',
    'extends',
    'components',
    'directives',
    'inheritAttrs',
    'compatConfig',
    'beforeCreate',
    'created',
    'beforeMount',
    'mounted',
    'beforeUpdate',
    'updated',
    'beforeUnmount',
    'unmounted',
    'beforeDestroy',
    'destroyed',
    'activated',
    'deactivated',
    'errorCaptured',
]);

const FACTORY_METHODS = new Set([
    'register',
    'extend',
    'override',
]);

function getPropertyName(property) {
    if (property.type !== 'Property' || property.computed) {
        return null;
    }

    return property.key.type === 'Identifier' ? property.key.name : String(property.key.value);
}

function isComponentOptions(node) {
    return (
        node?.type === 'ObjectExpression' &&
        node.properties.some((property) => COMPONENT_OPTION_KEYS.has(getPropertyName(property)))
    );
}

function getCalleeName(callExpression) {
    const { callee } = callExpression;

    if (callee.type === 'Identifier') {
        return callee.name;
    }

    return callee.type === 'MemberExpression' && !callee.computed ? callee.property.name : null;
}

// `Component` or `Shopware.Component`
function isComponentFactory(node) {
    return (
        (node.type === 'Identifier' && node.name === 'Component') ||
        (node.type === 'MemberExpression' && !node.computed && node.property.name === 'Component')
    );
}

/**
 * Returns how the options object is declared: `defineComponent`, `wrapComponentConfig`, `factory` for an inline
 * object passed to `Component.register()`, `extend()` or `override()`, `export` for a plain default export, or
 * null when the object is no component.
 */
function getComponentDeclaration(node) {
    if (!isComponentOptions(node)) {
        return null;
    }

    const { parent } = node;

    if (parent.type === 'ExportDefaultDeclaration') {
        return 'export';
    }

    if (parent.type !== 'CallExpression' || !parent.arguments.includes(node)) {
        return null;
    }

    const calleeName = getCalleeName(parent);

    if (calleeName === 'defineComponent' || calleeName === 'wrapComponentConfig') {
        return calleeName;
    }

    if (FACTORY_METHODS.has(calleeName) && parent.callee.type === 'MemberExpression' && isComponentFactory(parent.callee.object)) {
        return 'factory';
    }

    return null;
}

function findProperty(node, name) {
    return node.properties.find((property) => getPropertyName(property) === name);
}

module.exports = {
    findProperty,
    getComponentDeclaration,
};
