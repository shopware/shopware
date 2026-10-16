/**
 * @sw-package framework
 */

const isFunction = (value) => ['FunctionExpression', 'ArrowFunctionExpression'].includes(value.type);
const isObjectOrArray = (value) => ['ObjectExpression', 'ArrayExpression'].includes(value.type);
const isAnything = () => true;

// An object literal with one of these keys, holding a value of the shape a component uses, is a component.
// Checking the shape keeps `export default { provide, inject }` or `{ data: [] }` from counting.
const COMPONENT_OPTION_KEYS = new Map([
    ['template', isAnything],
    ['extends', isAnything],
    ['compatConfig', isAnything],
    ['inheritAttrs', (value) => value.type === 'Literal' && typeof value.value === 'boolean'],
    ['provide', (value) => isFunction(value) || value.type === 'ObjectExpression'],
    ...[
        'props',
        'emits',
        'computed',
        'methods',
        'watch',
        'inject',
        'mixins',
        'components',
        'directives',
    ].map((key) => [key, isObjectOrArray]),
    ...[
        'render',
        'setup',
        'data',
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
    ].map((key) => [key, isFunction]),
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

function isComponentOption(property) {
    const hasComponentShape = COMPONENT_OPTION_KEYS.get(getPropertyName(property));

    return !!hasComponentShape && hasComponentShape(property.value);
}

function isComponentOptions(node) {
    return node?.type === 'ObjectExpression' && node.properties.some(isComponentOption);
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
    getPropertyName,
};
