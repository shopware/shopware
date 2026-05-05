const RuleTester = require('eslint').RuleTester;
const rule = require('./require-override-priority');

const tester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2020,
        sourceType: 'module',
    },
});

tester.run('require-override-priority', rule, {
    valid: [
        {
            name: 'Component.override with explicit priority',
            code: `Component.override('foo', {}, Component.CONST.OVERRIDE_PRIORITY.CORE);`,
        },
        {
            name: 'Shopware.Component.override with explicit priority',
            code: `Shopware.Component.override('foo', {}, Shopware.Component.CONST.OVERRIDE_PRIORITY.STOREFRONT_ADMIN_MODULES);`,
        },
        {
            name: 'Component.override with numeric priority',
            code: `Component.override('foo', {}, -100);`,
        },
        {
            name: 'unrelated method call is ignored',
            code: `this.someMethod('foo', {});`,
        },
        {
            name: 'Component.register is ignored',
            code: `Component.register('foo', {});`,
        },
        {
            name: 'override called on different object',
            code: `something.override('foo', {});`,
        },
    ],
    invalid: [
        {
            name: 'Component.override without priority',
            code: `Component.override('foo', {});`,
            errors: [
                {
                    messageId: 'missingPriority',
                    suggestions: [
                        {
                            messageId: 'suggestCore',
                            output: `Component.override('foo', {}, Component.CONST.OVERRIDE_PRIORITY.CORE);`,
                        },
                        {
                            messageId: 'suggestStorefront',
                            output: `Component.override('foo', {}, Component.CONST.OVERRIDE_PRIORITY.STOREFRONT_ADMIN_MODULES);`,
                        },
                    ],
                },
            ],
        },
        {
            name: 'Shopware.Component.override without priority',
            code: `Shopware.Component.override('foo', {});`,
            errors: [
                {
                    messageId: 'missingPriority',
                    suggestions: [
                        {
                            messageId: 'suggestCore',
                            output: `Shopware.Component.override('foo', {}, Shopware.Component.CONST.OVERRIDE_PRIORITY.CORE);`,
                        },
                        {
                            messageId: 'suggestStorefront',
                            output: `Shopware.Component.override('foo', {}, Shopware.Component.CONST.OVERRIDE_PRIORITY.STOREFRONT_ADMIN_MODULES);`,
                        },
                    ],
                },
            ],
        },
        {
            name: 'Component.override with single argument',
            code: `Component.override('foo');`,
            errors: [{ messageId: 'missingPriority' }],
        },
        {
            name: 'Storefront-admin path surfaces STOREFRONT_ADMIN_MODULES suggestion first',
            filename: '/repo/src/Storefront/Resources/app/administration/src/extension/foo/index.js',
            code: `Component.override('foo', {});`,
            errors: [
                {
                    messageId: 'missingPriority',
                    suggestions: [
                        {
                            messageId: 'suggestStorefront',
                            output: `Component.override('foo', {}, Component.CONST.OVERRIDE_PRIORITY.STOREFRONT_ADMIN_MODULES);`,
                        },
                        {
                            messageId: 'suggestCore',
                            output: `Component.override('foo', {}, Component.CONST.OVERRIDE_PRIORITY.CORE);`,
                        },
                    ],
                },
            ],
        },
        {
            name: 'Core-admin path surfaces CORE suggestion first',
            filename: '/repo/src/Administration/Resources/app/administration/src/module/foo/index.js',
            code: `Component.override('foo', {});`,
            errors: [
                {
                    messageId: 'missingPriority',
                    suggestions: [
                        {
                            messageId: 'suggestCore',
                            output: `Component.override('foo', {}, Component.CONST.OVERRIDE_PRIORITY.CORE);`,
                        },
                        {
                            messageId: 'suggestStorefront',
                            output: `Component.override('foo', {}, Component.CONST.OVERRIDE_PRIORITY.STOREFRONT_ADMIN_MODULES);`,
                        },
                    ],
                },
            ],
        },
    ],
});
