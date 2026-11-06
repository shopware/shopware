const RuleTester = require('eslint').RuleTester;
const rule = require('./require-deprecation-codemod');

const tester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const missingCodemod = /has no codemod for it[\s\S]*eslint-disable-next-line sw-deprecation-rules\/require-deprecation-codemod -- <why no codemod is possible>/;

tester.run('require-deprecation-codemod', rule, {
    valid: [
        {
            name: 'allows a deprecated component the registry replaces',
            code: "Shopware.Component.register('sw-alert', { deprecated: 'v6.8.0.0' });",
        },
        {
            name: 'allows a deprecated prop the registry migrates, written in kebab-case there',
            filename: 'src/app/component/entity/sw-entity-listing/index.js',
            code: "export default { props: { items: { type: Array, deprecated: 'v6.9.0.0' } } };",
        },
        {
            name: 'ignores a prop that is named "deprecated"',
            filename: 'src/app/component/form/sw-foo/index.ts',
            code: 'export default Shopware.Component.wrapComponentConfig({ props: { deprecated: { type: Boolean } } });',
        },
        {
            name: 'ignores components without a deprecated option',
            code: "Shopware.Component.register('sw-foo', { template: '<div />' });",
        },
    ],
    invalid: [
        {
            name: 'reports a registered component without codemod',
            code: "Shopware.Component.register('sw-foo', { deprecated: 'v6.8.0.0' });",
            errors: [{ message: /The component "sw-foo" is marked `deprecated`/ }],
        },
        {
            name: 'reports a wrapped component without codemod by its directory name',
            filename: 'src/app/component/base/sw-foo/index.ts',
            code: "export default Shopware.Component.wrapComponentConfig({ deprecated: { version: 'v6.8.0.0' } });",
            errors: [{ message: /The component "sw-foo"/ }],
        },
        {
            name: 'reports a defineComponent component without codemod by its name option',
            code: "export default defineComponent({ name: 'sw-foo', deprecated: 'v6.8.0.0' });",
            errors: [{ message: /The component "sw-foo"/ }],
        },
        {
            name: 'reports a deprecated prop without codemod and says how to fix it',
            filename: 'src/app/component/entity/sw-entity-listing/index.js',
            code: "export default { props: { columns: { type: Array, deprecated: 'v6.9.0.0' } } };",
            errors: [{ message: missingCodemod }],
        },
        {
            name: 'reports a component that only has prop codemods as missing a component codemod',
            filename: 'src/app/component/entity/sw-entity-listing/index.js',
            code: "export default { deprecated: 'v6.9.0.0' };",
            errors: [{ message: /The component "sw-entity-listing"/ }],
        },
    ],
});
