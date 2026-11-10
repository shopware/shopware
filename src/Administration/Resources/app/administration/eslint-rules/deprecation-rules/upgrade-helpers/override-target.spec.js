/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const { targetAt, fileTargets } = require('./override-target');

const rule = {
    meta: { type: 'problem', schema: [] },
    create(context) {
        return {
            'Program:exit'() {
                context.report({ loc: { line: 1, column: 0 }, message: `file:${fileTargets(context).join(',') || 'none'}` });
            },
            MemberExpression(node) {
                if (node.object.type === 'ThisExpression' && node.property.name === 'probe') {
                    context.report({ node, message: `at:${targetAt(context, node) ?? 'none'}` });
                }
            },
        };
    },
};

new RuleTester({
    languageOptions: { parser: require('@typescript-eslint/parser'), ecmaVersion: 2022, sourceType: 'module' },
}).run('override-target', rule, {
    valid: [],
    invalid: [
        {
            name: 'finds the target of an override in the same file',
            code: "Shopware.Component.override('sw-order-list', { computed: { a() { return this.probe; } } });",
            errors: [{ message: 'file:sw-order-list' }, { message: 'at:sw-order-list' }],
        },
        {
            name: 'uses the base of an extension',
            code: "Component.extend('acme-list', 'sw-order-list', { methods: { a() { this.probe(); } } });",
            errors: [{ message: 'file:sw-order-list' }, { message: 'at:sw-order-list' }],
        },
        {
            name: 'tells several overrides in one file apart',
            code: [
                "Component.override('sw-a', { methods: { a() { this.probe(); } } });",
                "Component.override('sw-b', { methods: { b() { this.probe(); } } });",
            ].join('\n'),
            errors: [{ message: 'file:sw-a,sw-b' }, { message: 'at:sw-a' }, { message: 'at:sw-b' }],
        },
        {
            name: 'falls back to a core component folder name for export default',
            filename: '/plugin/src/extension/sw-order-list/index.js',
            code: 'export default { methods: { a() { this.probe(); } } };',
            errors: [{ message: 'file:sw-order-list' }, { message: 'at:sw-order-list' }],
        },
        {
            name: 'unwraps wrapComponentConfig and TypeScript casts',
            filename: '/plugin/src/extension/sw-order-list/index.ts',
            code: 'export default Shopware.Component.wrapComponentConfig({ methods: { a() { this.probe(); } } }) as never;',
            errors: [{ message: 'file:sw-order-list' }, { message: 'at:sw-order-list' }],
        },
        {
            name: 'has no target in a plugin folder',
            filename: '/plugin/src/component/acme-list/index.js',
            code: 'export default { methods: { a() { this.probe(); } } };',
            errors: [{ message: 'file:none' }, { message: 'at:none' }],
        },
    ],
});
