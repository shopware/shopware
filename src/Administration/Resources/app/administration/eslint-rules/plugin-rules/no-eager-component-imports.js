/**
 * @sw-package framework
 *
 * Reports component configs that look lazy but are fetched while the extension boots.
 *
 * // Invalid: the import starts or has finished before the component is rendered
 * Shopware.Component.register('swag-example', import('./swag-example'));
 * import config from './swag-example'; Shopware.Component.register('swag-example', () => config);
 *
 * // Valid: the import starts on first render
 * Shopware.Component.register('swag-example', () => import('./swag-example'));
 */

const COMPONENT_METHODS = ['register', 'override', 'extend'];
const TYPE_WRAPPERS = ['TSAsExpression', 'TSSatisfiesExpression', 'TSNonNullExpression'];

function unwrap(node) {
    return node && TYPE_WRAPPERS.includes(node.type) ? unwrap(node.expression) : node;
}

function findVariable(context, identifier) {
    for (let scope = context.sourceCode.getScope(identifier); scope; scope = scope.upper) {
        const variable = scope.set.get(identifier.name);
        if (variable) {
            return variable;
        }
    }

    return null;
}

// The only definition of an identifier that is never reassigned, so its value is known statically.
function stableDefinitionOf(context, node) {
    const identifier = unwrap(node);
    if (identifier?.type !== 'Identifier') {
        return null;
    }

    const variable = findVariable(context, identifier);
    if (variable?.defs.length !== 1) {
        return null;
    }

    const [definition] = variable.defs;
    if (definition.type === 'Variable' && definition.parent.kind !== 'const') {
        return null;
    }

    return definition;
}

function initOf(definition) {
    return definition?.type === 'Variable' ? unwrap(definition.node.init) : null;
}

function isGlobal(context, node, name) {
    return node.type === 'Identifier' && node.name === name && !findVariable(context, node)?.defs.length;
}

function isShopware(context, node) {
    if (node.type === 'MemberExpression' && !node.computed && node.property.name === 'Shopware') {
        return isGlobal(context, node.object, 'window');
    }

    return isGlobal(context, node, 'Shopware');
}

// `Shopware.Component`, `window.Shopware.Component` or `const { Component } = Shopware`
function isComponentFactory(context, node) {
    if (node.type === 'MemberExpression') {
        return !node.computed && node.property.name === 'Component' && isShopware(context, node.object);
    }

    const definition = stableDefinitionOf(context, node);
    const init = initOf(definition);
    if (!init || definition.node.id.type !== 'ObjectPattern') {
        return false;
    }

    const property = definition.node.id.properties.find((item) => item.value === definition.name);

    return property?.key.type === 'Identifier' && property.key.name === 'Component' && isShopware(context, init);
}

function isFunction(node) {
    return node?.type === 'ArrowFunctionExpression' || node?.type === 'FunctionExpression';
}

function isWithin(node, container) {
    return node.range[0] >= container.range[0] && node.range[1] <= container.range[1];
}

// `import('./a')` or `import('./a').then(...)`
function isImportCall(node) {
    const expression = unwrap(node);
    if (expression?.type === 'ImportExpression') {
        return true;
    }

    return (
        expression?.type === 'CallExpression' &&
        expression.callee.type === 'MemberExpression' &&
        isImportCall(expression.callee.object)
    );
}

// An `import()` that runs when the surrounding code runs, not when the loader is called.
function isStartedImport(context, node, loader = null) {
    if (isImportCall(node)) {
        return true;
    }

    const definition = stableDefinitionOf(context, node);

    return isImportCall(initOf(definition)) && !(loader && isWithin(definition.node, loader));
}

function moduleImportOf(context, node) {
    const definition = stableDefinitionOf(context, node);
    const isValueImport =
        definition?.type === 'ImportBinding' &&
        definition.parent.type === 'ImportDeclaration' &&
        definition.parent.importKind !== 'type' &&
        definition.node.importKind !== 'type';

    return isValueImport ? definition : null;
}

function isImportedVueComponent(context, node) {
    const definition = moduleImportOf(context, node);
    const isDefault =
        definition?.node.type === 'ImportDefaultSpecifier' ||
        (definition?.node.type === 'ImportSpecifier' && definition.node.imported.name === 'default');

    return isDefault && definition.parent.source.value.endsWith('.vue');
}

function loaderOf(context, node) {
    const expression = unwrap(node);
    if (isFunction(expression)) {
        return expression;
    }

    const definition = stableDefinitionOf(context, expression);
    if (definition?.type === 'FunctionName') {
        return definition.node.body ? definition.node : null;
    }

    const init = initOf(definition);

    return isFunction(init) ? init : null;
}

function returnedValues(loader) {
    if (loader.body.type !== 'BlockStatement') {
        return [loader.body];
    }

    return loader.body.body
        .filter((statement) => statement.type === 'ReturnStatement' && statement.argument)
        .map((statement) => statement.argument);
}

function returnsLoadedModule(context, loader) {
    return returnedValues(loader).some((value) => {
        const expression = unwrap(value.type === 'AwaitExpression' ? value.argument : value);

        return (
            expression.type === 'Identifier' &&
            (moduleImportOf(context, expression) !== null || isStartedImport(context, expression, loader))
        );
    });
}

function isEagerConfig(context, config) {
    if (isStartedImport(context, config) || isImportedVueComponent(context, config)) {
        return true;
    }

    const loader = loaderOf(context, config);

    return loader !== null && returnsLoadedModule(context, loader);
}

module.exports = {
    meta: {
        type: 'suggestion',
        docs: {
            description: 'Load component configs inside a loader function so they are fetched on first render',
        },
        schema: [],
        messages: {
            eager:
                'This component config is loaded while the extension boots. ' +
                "Pass a loader function that starts the import instead, e.g. () => import('./my-component').",
        },
    },
    create(context) {
        return {
            CallExpression(node) {
                const callee = node.callee;
                if (
                    callee.type !== 'MemberExpression' ||
                    callee.computed ||
                    !COMPONENT_METHODS.includes(callee.property.name) ||
                    !isComponentFactory(context, callee.object)
                ) {
                    return;
                }

                const config = node.arguments[callee.property.name === 'extend' ? 2 : 1];
                if (config && isEagerConfig(context, config)) {
                    context.report({ node: config, messageId: 'eager' });
                }
            },
        };
    },
};
