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
    ],
    invalid: [
        {
            name: 'migrates deprecated Admin Extension SDK package import',
            code: 'import { ui } from "@shopware-ag/admin-extension-sdk";',
            output: 'import { ui } from \'@shopware-ag/meteor-admin-sdk\';',
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
        {
            name: 'migrates generic CMS element publishData identifiers',
            code: "Shopware.ExtensionAPI.publishData({ id: publishingKey, path: 'element' });",
            output: "Shopware.ExtensionAPI.publishData({ id: `${publishingKey}__${element.id}`, path: 'element' });",
            errors: [
                {
                    message: /Generic CMS element publishData identifiers are deprecated/,
                },
            ],
        },
        {
            name: 'migrates generic CMS element publishData identifiers with double quoted path',
            code: 'Shopware.ExtensionAPI.publishData({ id: publishingKey, path: "element" });',
            output: "Shopware.ExtensionAPI.publishData({ id: `${publishingKey}__${element.id}`, path: 'element' });",
            errors: [
                {
                    message: /Generic CMS element publishData identifiers are deprecated/,
                },
            ],
        },
        {
            name: 'migrates generic CMS element publishData identifiers with trailing object comma',
            code: "Shopware.ExtensionAPI.publishData({ id: publishingKey, path: 'element', });",
            output: "Shopware.ExtensionAPI.publishData({ id: `${publishingKey}__${element.id}`, path: 'element' });",
            errors: [
                {
                    message: /Generic CMS element publishData identifiers are deprecated/,
                },
            ],
        },
        {
            name: 'migrates generic CMS element publishData identifiers with reordered object properties',
            code: "Shopware.ExtensionAPI.publishData({ path: 'element', id: publishingKey });",
            output: "Shopware.ExtensionAPI.publishData({ id: `${publishingKey}__${element.id}`, path: 'element' });",
            errors: [
                {
                    message: /Generic CMS element publishData identifiers are deprecated/,
                },
            ],
        },
        {
            name: 'reports generic CMS element publishData identifiers with extra object properties without autofix',
            code: "Shopware.ExtensionAPI.publishData({ id: publishingKey, path: 'element', scope: 'custom' });",
            output: null,
            errors: [
                {
                    message: /Generic CMS element publishData identifiers are deprecated/,
                },
            ],
        },
        {
            name: 'reports Options API component overrides without autofix',
            code: `
                Shopware.Component.override('sw-example', {
                    methods: {},
                });
            `,
            output: null,
            errors: [
                {
                    message: /Options API component overrides are deprecated/,
                },
            ],
        },
    ],
});
