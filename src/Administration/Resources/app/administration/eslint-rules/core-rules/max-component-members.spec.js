const { RuleTester } = require('eslint');
const rule = require('./max-component-members');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const options = [{ max: 4 }];

ruleTester.run('max-component-members', rule, {
    valid: [
        {
            name: 'allows a component at the limit',
            code: `export default {
                props: { a: String, b: String, c: String },
                data() { return { isLoading: false }; },
                computed: { total() {} },
                watch: { total() {} },
                methods: { save() {} },
            };`,
            options,
        },
        {
            name: 'ignores objects that are no component',
            code: 'export const labels = { a: 1, b: 2, c: 3, d: 4, e: 5 };',
            options,
        },
    ],
    invalid: [
        {
            name: 'counts methods, computed properties, watchers and data keys',
            code: `export default defineComponent({
                data: () => ({ isLoading: false, items: [] }),
                computed: { ...mapState(['total']) },
                watch: { total() {} },
                methods: { save() {} },
            });`,
            options,
            errors: [
                {
                    message:
                        'Component has 5 methods, computed properties, watchers and data keys. Maximum allowed is 4.',
                },
            ],
        },
        {
            name: 'reads the data keys from the returned object',
            code: `Shopware.Component.register('sw-foo', {
                data() {
                    const defaults = {};
                    return { a: 1, b: 2, c: 3, d: 4, e: defaults };
                },
            });`,
            options,
            errors: [
                {
                    message:
                        'Component has 5 methods, computed properties, watchers and data keys. Maximum allowed is 4.',
                },
            ],
        },
    ],
});
