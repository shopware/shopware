/**
 * @sw-package framework
 *
 * Overview
 * This custom ESLint rule enforces that component configs are loaded when the component is first used
 * instead of at boot. Component.register must receive the config as an anonymous arrow function.
 * Component.extend and Component.override may pass an inline config, but must not start an import at boot.
 *
 * Example
 *
 * // Invalid
 * Shopware.Component.register('sw-example', { template: '<div>Example</div>' });
 * Component.register('sw-example', import('./sw-example'));
 * Component.extend('sw-example-child', 'sw-example', import('./sw-example-child'));
 * Component.override('sw-example', import('./sw-example-override'));
 *
 * // Valid
 * Shopware.Component.register('sw-example', () => import('./sw-example'));
 * Component.extend('sw-example-child', 'sw-example', () => import('./sw-example-child'));
 * Component.override('sw-example', () => import('./sw-example-override'));
 * Component.override('sw-example', { methods: {} });
 *
 */
const isNotLazy = (config) => config?.type !== 'ArrowFunctionExpression';
const isImportedAtBoot = (config) => config?.type === 'ImportExpression';

const configRules = new Map([
    ['register', { configIndex: 1, isEager: isNotLazy }],
    ['extend', { configIndex: 2, isEager: isImportedAtBoot }],
    ['override', { configIndex: 1, isEager: isImportedAtBoot }],
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
                'enforce that Shopware.Component.register, extend and override calls do not load the component config at boot',
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

                const configRule = configRules.get(callee.property.name);
                if (!configRule) {
                    return;
                }

                const config = node.arguments[configRule.configIndex];
                if (!configRule.isEager(config)) {
                    return;
                }

                context.report({
                    node: config ?? node,
                    message: "The component config of {{ call }} must be a dynamic import: () => import('./sw-example')",
                    data: { call: context.sourceCode.getText(callee) },
                });
            },
        };
    },
};
