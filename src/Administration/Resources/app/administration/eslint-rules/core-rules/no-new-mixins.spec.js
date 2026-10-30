const { RuleTester } = require('eslint');
const rule = require('./no-new-mixins');

const ruleTester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const message = 'Do not register new mixins. Share the logic as a composable in src/app/composables instead.';

ruleTester.run('no-new-mixins', rule, {
    valid: [
        {
            name: 'allows using an existing mixin',
            code: "export default { mixins: [Shopware.Mixin.getByName('notification')] };",
        },
        {
            name: 'allows registering components',
            code: "Shopware.Component.register('sw-video-cover', () => import('./sw-video-cover'));",
        },
    ],
    invalid: [
        {
            name: 'reports a mixin registered through the global object',
            code: "Shopware.Mixin.register('video-cover', { methods: {} });",
            errors: [{ message }],
        },
        {
            name: 'reports a mixin registered through the destructured factory',
            code: "const { Mixin } = Shopware; Mixin.register('video-cover', { methods: {} });",
            errors: [{ message }],
        },
    ],
});
