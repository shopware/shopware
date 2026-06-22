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
