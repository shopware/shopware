/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const { fileTargets } = require('./override-target');

const rule = {
    meta: { type: 'problem', schema: [] },
    create(context) {
        return {
            'Program:exit'() {
                context.report({ loc: { line: 1, column: 0 }, message: `file:${fileTargets(context).join(',') || 'none'}` });
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
            code: "Shopware.Component.override('sw-order-list', { computed: { a() { return this.isLoading; } } });",
            errors: [{ message: 'file:sw-order-list' }],
        },
        {
            name: 'finds every override and extension in one file',
            code: [
                "Component.override('sw-a', { methods: { a() { this.isLoading(); } } });",
                "Component.extend('acme-b', 'sw-b', { methods: { b() { this.isLoading(); } } });",
            ].join('\n'),
            errors: [{ message: 'file:sw-a,sw-b' }],
        },
        {
            name: 'falls back to a core component folder name for export default',
            filename: '/plugin/src/extension/sw-order-list/index.js',
            code: 'export default { methods: { a() { this.isLoading(); } } };',
            errors: [{ message: 'file:sw-order-list' }],
        },
        {
            name: 'unwraps wrapComponentConfig and TypeScript casts',
            filename: '/plugin/src/extension/sw-order-list/index.ts',
            code: 'export default Shopware.Component.wrapComponentConfig({ methods: { a() { this.isLoading(); } } }) as never;',
            errors: [{ message: 'file:sw-order-list' }],
        },
        {
            name: 'has no target in a plugin folder',
            filename: '/plugin/src/component/acme-list/index.js',
            code: 'export default { methods: { a() { this.isLoading(); } } };',
            errors: [{ message: 'file:none' }],
        },
    ],
});
