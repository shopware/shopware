/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-private-composition-api');
const data = require('./no-private-composition-api.data');

const OVERRIDE = data.overrideMessage('sw-product-list');
const OVERRIDE_UNKNOWN = data.overrideMessage(null);
const IMPORT = data.imports[0].message;
const todo = (text) => `// TODO Codemod(6.8, composition-api-private): ${text}`;

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-private-composition-api', rule, {
    valid: [
        { name: 'ignores Component.override without private API', code: "Shopware.Component.override('sw-product-list', { methods: {} });" },
        { name: 'ignores the public component factory', code: "const { Component } = Shopware;\nComponent.extend('acme-list', 'sw-product-list', {});" },
        { name: 'ignores own functions with the same name', code: 'function attachOverrides() {}\nattachOverrides();' },
        { name: 'ignores the other members of Shopware.Component', code: 'const { getComponentHelper } = Shopware.Component;' },
    ],
    invalid: [
        {
            name: 'marks the curried overrideComponentSetup() with the target name',
            code: [
                "Shopware.Component.overrideComponentSetup()('sw-product-list', (previousState) => ({",
                '    columns: previousState.columns,',
                '}));',
            ].join('\n'),
            output: [
                todo(OVERRIDE),
                "Shopware.Component.overrideComponentSetup()('sw-product-list', (previousState) => ({",
                '    columns: previousState.columns,',
                '}));',
            ].join('\n'),
            errors: [{ message: OVERRIDE }],
        },
        {
            name: 'marks overrideComponentSetup() through a Component alias and without a literal target',
            code: ['const { Component } = Shopware;', 'Component.overrideComponentSetup()(name, setup);', 'const register = Component.overrideComponentSetup;'].join('\n'),
            output: [
                'const { Component } = Shopware;',
                todo(OVERRIDE_UNKNOWN),
                'Component.overrideComponentSetup()(name, setup);',
                todo(OVERRIDE_UNKNOWN),
                'const register = Component.overrideComponentSetup;',
            ].join('\n'),
            errors: [{ message: OVERRIDE_UNKNOWN }, { message: OVERRIDE_UNKNOWN }],
        },
        {
            name: 'marks the other private functions',
            code: [
                "const setup = Shopware.Component.createExtendableSetup({ name: 'acme-card', props }, () => ({ public: {} }));",
                "Shopware.Component.attachOverrides({ name: 'acme-card', public: {} });",
                'const exposed = Component.getExposedProps();',
                "Shopware.Component.registerNativeExtensionTargets?.({ component: 'sw-dashboard-index' });",
                'Shopware.Component.registerOverrideComponent(AcmeOverride);',
                'Shopware.Component.getOverrideComponents();',
            ].join('\n'),
            output: [
                todo(data.functions.createExtendableSetup),
                "const setup = Shopware.Component.createExtendableSetup({ name: 'acme-card', props }, () => ({ public: {} }));",
                todo(data.functions.attachOverrides),
                "Shopware.Component.attachOverrides({ name: 'acme-card', public: {} });",
                todo(data.functions.getExposedProps),
                'const exposed = Component.getExposedProps();',
                todo(data.functions.registerNativeExtensionTargets),
                "Shopware.Component.registerNativeExtensionTargets?.({ component: 'sw-dashboard-index' });",
                todo(data.functions.registerOverrideComponent),
                'Shopware.Component.registerOverrideComponent(AcmeOverride);',
                todo(data.functions.getOverrideComponents),
                'Shopware.Component.getOverrideComponents();',
            ].join('\n'),
            errors: [
                { message: data.functions.createExtendableSetup },
                { message: data.functions.attachOverrides },
                { message: data.functions.getExposedProps },
                { message: data.functions.registerNativeExtensionTargets },
                { message: data.functions.registerOverrideComponent },
                { message: data.functions.getOverrideComponents },
            ],
        },
        {
            name: 'marks destructuring from Shopware.Component and calls through the local names',
            code: [
                'const { overrideComponentSetup, createExtendableSetup: extendable } = Shopware.Component;',
                "overrideComponentSetup()('sw-product-list', setup);",
                'extendable(options, setup);',
            ].join('\n'),
            // Both destructured names share the first line; the second comment follows on the next fix pass.
            output: [
                todo(OVERRIDE_UNKNOWN),
                'const { overrideComponentSetup, createExtendableSetup: extendable } = Shopware.Component;',
                todo(OVERRIDE),
                "overrideComponentSetup()('sw-product-list', setup);",
                todo(data.functions.createExtendableSetup),
                'extendable(options, setup);',
            ].join('\n'),
            errors: [
                { message: OVERRIDE_UNKNOWN },
                { message: data.functions.createExtendableSetup },
                { message: OVERRIDE },
                { message: data.functions.createExtendableSetup },
            ],
        },
        {
            name: 'marks imports of the private module',
            code: [
                "import { overrideComponentSetup } from 'src/app/adapter/composition-extension-system';",
                "import helper from 'src/app/adapter/composition-extension-system/data-scope-helper';",
            ].join('\n'),
            output: [
                todo(IMPORT),
                "import { overrideComponentSetup } from 'src/app/adapter/composition-extension-system';",
                todo(IMPORT),
                "import helper from 'src/app/adapter/composition-extension-system/data-scope-helper';",
            ].join('\n'),
            errors: [{ message: IMPORT }, { message: IMPORT }],
        },
    ],
});

new RuleTester({ languageOptions: { parser: require('@typescript-eslint/parser'), ecmaVersion: 2022, sourceType: 'module' } }).run(
    'no-private-composition-api (TypeScript)',
    rule,
    {
        valid: [],
        invalid: [
            {
                name: 'marks the typed curried form',
                code: "Shopware.Component.overrideComponentSetup<typeof SwProductList>()('sw-product-list', setup);",
                output: [todo(OVERRIDE), "Shopware.Component.overrideComponentSetup<typeof SwProductList>()('sw-product-list', setup);"].join('\n'),
                errors: [{ message: OVERRIDE }],
            },
        ],
    },
);
