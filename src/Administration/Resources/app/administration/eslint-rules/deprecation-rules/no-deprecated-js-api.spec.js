const RuleTester = require('eslint').RuleTester;
const rule = require('./no-deprecated-js-api');

const tester = new RuleTester({
    languageOptions: {
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

tester.run('no-deprecated-js-api', rule, {
    valid: [
        {
            name: 'allows metaInfo function',
            code: `
                export default {
                    metaInfo() {
                        return { title: 'Example' };
                    },
                };
            `,
        },
        {
            name: 'leaves a metaInfo object outside component options alone',
            code: `
                store.save({ metaInfo: { title: 'Example' } });
            `,
        },
        {
            name: 'allows Options API component overrides, which stay supported for Options API targets',
            code: `
                Shopware.Component.override('sw-example', {
                    methods: {},
                });
            `,
        },
        {
            name: 'allows unrelated property',
            code: `
                export default {
                    title: { text: 'Example' },
                };
            `,
        },
        {
            name: 'allows current Meteor Admin SDK package',
            code: 'import { ui } from "@shopware-ag/meteor-admin-sdk";',
        },
        {
            name: 'allows unrelated object computePath calls',
            code: `
                export default {
                    methods: {
                        check(event) {
                            return pathHelper.computePath(event);
                        },
                    },
                };
            `,
        },
        {
            name: 'allows unrelated object loadConfigSettingGroups calls',
            code: `
                export default {
                    methods: {
                        load() {
                            configService.loadConfigSettingGroups();
                        },
                    },
                };
            `,
        },
        {
            name: 'allows unrelated object setAppModules calls',
            code: `
                const other = {
                    setAppModules() {},
                };

                other.setAppModules();
            `,
        },
        {
            name: 'allows a package that only shares the deprecated package name prefix',
            code: "import { ui } from '@shopware-ag/admin-extension-sdk-foo'; export * from '@shopware-ag/admin-extension-sdk-foo/es';",
        },
        {
            name: 'ignores exports and requires that name no package',
            code: 'export const x = 1; export { x as y }; const m = require(name); require();',
        },
    ],
    invalid: [
        {
            name: 'migrates a subpath import of the deprecated Admin Extension SDK',
            code: "import { ui } from '@shopware-ag/admin-extension-sdk/es/foo';",
            output: "import { ui } from '@shopware-ag/meteor-admin-sdk/es/foo';",
            errors: [
                {
                    message: /@shopware-ag\/admin-extension-sdk/,
                },
            ],
        },
        {
            name: 'migrates an export-all from the deprecated Admin Extension SDK',
            code: "export * from '@shopware-ag/admin-extension-sdk';",
            output: "export * from '@shopware-ag/meteor-admin-sdk';",
            errors: [
                {
                    message: /@shopware-ag\/admin-extension-sdk/,
                },
            ],
        },
        {
            name: 'migrates a named re-export from the deprecated Admin Extension SDK',
            code: "export { ui } from '@shopware-ag/admin-extension-sdk';",
            output: "export { ui } from '@shopware-ag/meteor-admin-sdk';",
            errors: [
                {
                    message: /@shopware-ag\/admin-extension-sdk/,
                },
            ],
        },
        {
            name: 'migrates a dynamic import of the deprecated Admin Extension SDK',
            code: "const sdk = import('@shopware-ag/admin-extension-sdk/es/foo');",
            output: "const sdk = import('@shopware-ag/meteor-admin-sdk/es/foo');",
            errors: [
                {
                    message: /@shopware-ag\/admin-extension-sdk/,
                },
            ],
        },
        {
            name: 'migrates a require of the deprecated Admin Extension SDK',
            code: "const sdk = require('@shopware-ag/admin-extension-sdk');",
            output: "const sdk = require('@shopware-ag/meteor-admin-sdk');",
            errors: [
                {
                    message: /@shopware-ag\/admin-extension-sdk/,
                },
            ],
        },
        {
            name: 'migrates deprecated Admin Extension SDK package import',
            code: 'import { ui } from "@shopware-ag/admin-extension-sdk";',
            output: 'import { ui } from "@shopware-ag/meteor-admin-sdk";',
            errors: [
                {
                    message: /@shopware-ag\/admin-extension-sdk/,
                },
            ],
        },
        {
            name: 'reports deprecated Admin Extension SDK package import without fix [disableFix]',
            options: ['disableFix'],
            code: 'import { ui } from "@shopware-ag/admin-extension-sdk";',
            output: null,
            errors: [
                {
                    message: /@shopware-ag\/admin-extension-sdk/,
                },
            ],
        },
        {
            name: 'migrates metaInfo object to function',
            code: `
                export default {
                    metaInfo: { title: 'Example' },
                };
            `,
            output: `
                export default {
                    metaInfo() { return { title: 'Example' }; },
                };
            `,
            errors: [
                {
                    message: /Providing metaInfo as an object is not supported anymore/,
                },
            ],
        },
        {
            name: 'migrates a metaInfo object in a component override',
            code: `
                Shopware.Component.override('sw-example', {
                    metaInfo: { title: 'Example' },
                });
            `,
            output: `
                Shopware.Component.override('sw-example', {
                    metaInfo() { return { title: 'Example' }; },
                });
            `,
            errors: [
                {
                    message: /Providing metaInfo as an object is not supported anymore/,
                },
            ],
        },
        {
            name: 'reports removed shopwareApps setAppModules store call',
            code: `
                Shopware.Store.get('shopwareApps').setAppModules();
            `,
            output: null,
            errors: [
                {
                    message: /setAppModules action/,
                },
            ],
        },
        {
            name: 'reports removed shopwareApps setAppModules store call with double quoted store name',
            code: `
                Shopware.Store.get("shopwareApps").setAppModules();
            `,
            output: null,
            errors: [
                {
                    message: /setAppModules action/,
                },
            ],
        },
        {
            name: 'reports removed loadConfigSettingGroups call',
            code: `
                export default {
                    methods: {
                        load() {
                            this.loadConfigSettingGroups();
                        },
                    },
                };
            `,
            output: null,
            errors: [
                {
                    message: /loadConfigSettingGroups\(\) method/,
                },
            ],
        },
        {
            name: 'reports removed computePath call',
            code: `
                export default {
                    methods: {
                        check(event) {
                            return this.computePath(event).includes(this.$el);
                        },
                    },
                };
            `,
            output: null,
            errors: [
                {
                    message: /Use Element\.contains\(\)/,
                },
            ],
        },
    ],
});
