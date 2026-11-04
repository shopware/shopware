/**
 * @sw-package framework
 *
 * Overview
 * This custom ESLint rule enforces that Component.register, Component.extend and Component.override
 * receive the component config as an anonymous arrow function. This ensures components are loaded
 * with dynamic imports when they are first used instead of at boot.
 *
 * Example
 *
 * // Invalid
 * Shopware.Component.register('sw-example', { template: '<div>Example</div>' });
 * Component.register('sw-example', import('./sw-example'));
 * Component.extend('sw-example-child', 'sw-example', import('./sw-example-child'));
 *
 * // Valid
 * Shopware.Component.register('sw-example', () => import('./sw-example'));
 * Component.extend('sw-example-child', 'sw-example', () => import('./sw-example-child'));
 * Component.override('sw-example', () => import('./sw-example-override'));
 *
 */
const configArgumentIndex = new Map([
    ['register', 1],
    ['extend', 2],
    ['override', 1],
]);

function isComponentFacade(node) {
    if (node.type === 'Identifier') {
        return node.name === 'Component';
    }

    return node.type === 'MemberExpression' && node.object.name === 'Shopware' && node.property.name === 'Component';
}

module.exports = {
    meta: {
        type: 'problem',
        docs: {
            description:
                'enforce that Shopware.Component.register, extend and override calls use an anonymous arrow function as the component config',
            category: 'Possible Errors',
            recommended: true,
        },
        schema: [],
    },
    create(context) {
        return {
            CallExpression(node) {
                const { callee } = node;
                if (callee.type !== 'MemberExpression' || !isComponentFacade(callee.object)) {
                    return;
                }

                const index = configArgumentIndex.get(callee.property.name);
                if (index === undefined) {
                    return;
                }

                const call = context.sourceCode.getText(callee);
                const config = node.arguments[index];

                if (!config) {
                    context.report({
                        node,
                        message: '{{ call }} requires at least {{ count }} arguments',
                        data: { call, count: index + 1 },
                    });
                    return;
                }

                if (config.type === 'ArrowFunctionExpression') {
                    return;
                }

                context.report({
                    node: config,
                    message: "The component config of {{ call }} must be a dynamic import: () => import('./sw-example')",
                    data: { call },
                });
            },
        };
    },
};
