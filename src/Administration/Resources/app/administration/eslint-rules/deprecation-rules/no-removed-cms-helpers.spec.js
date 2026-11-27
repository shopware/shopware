/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-removed-cms-helpers');
const data = require('./no-removed-cms-helpers.data');

const message = (name) => data.members.find((entry) => entry.name === name).message;
const RESET = message('resetCmsPageState');
const EXTRACT = message('extractSlotOverrides');
const OVERRIDES = message('getCmsPageOverrides');
const DELETE = message('deleteSpecifcKeys');
const todo = (text) => `// TODO Codemod(6.8, cms-helpers): ${text}`;

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-removed-cms-helpers', rule, {
    valid: [
        {
            name: 'ignores the store action resetCmsPageState',
            code: [
                "Shopware.Store.get('cmsPage').resetCmsPageState();",
                'this.cmsPageState.resetCmsPageState();',
                "Component.override('sw-cms-detail', { methods: { onReset() { this.cmsPageState.resetCmsPageState(); } } });",
            ].join('\n'),
        },
        {
            name: 'ignores resetCmsPageState inside the actions of a store',
            code: "Shopware.Store.register({ id: 'acme', actions: { reset() { this.resetCmsPageState(); }, resetCmsPageState() {} } });",
        },
        {
            name: 'ignores a method named resetCmsPageState outside sw-cms-detail',
            code: "Component.override('sw-product-detail-layout', { methods: { resetCmsPageState() {} } });",
        },
        { name: 'ignores the replacement', code: "Component.override('sw-cms-detail', { methods: { resetRelatedStores() { this.$super('resetRelatedStores'); } } });" },
    ],
    invalid: [
        {
            name: 'marks resetCmsPageState in overrides of sw-cms-detail, without a second TODO for its $super call',
            code: [
                "Component.override('sw-cms-detail', {",
                '    methods: {',
                '        resetCmsPageState() {',
                "            this.$super('resetCmsPageState');",
                '        },',
                '        onSave() {',
                '            this.resetCmsPageState();',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            output: [
                "Component.override('sw-cms-detail', {",
                '    methods: {',
                `        ${todo(RESET)}`,
                '        resetCmsPageState() {',
                "            this.$super('resetCmsPageState');",
                '        },',
                '        onSave() {',
                `            ${todo(RESET)}`,
                '            this.resetCmsPageState();',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            errors: [{ message: RESET }, { message: RESET }],
        },
        {
            name: 'marks resetCmsPageState in extensions of sw-cms-create and this calls anywhere',
            code: [
                "Component.extend('acme-cms-create', 'sw-cms-create', { methods: { resetCmsPageState() {} } });",
                'export default { methods: { onLeave() { this.resetCmsPageState(); } } };',
            ].join('\n'),
            output: [
                todo(RESET),
                "Component.extend('acme-cms-create', 'sw-cms-create', { methods: { resetCmsPageState() {} } });",
                todo(RESET),
                'export default { methods: { onLeave() { this.resetCmsPageState(); } } };',
            ].join('\n'),
            errors: [{ message: RESET }, { message: RESET }],
        },
        {
            name: 'marks $super calls of the helpers from other methods',
            code: "Component.override('sw-cms-detail', { methods: { onSave() { this.$super('resetCmsPageState'); } } });",
            output: [todo(RESET), "Component.override('sw-cms-detail', { methods: { onSave() { this.$super('resetCmsPageState'); } } });"].join('\n'),
            errors: [{ message: RESET }],
        },
        {
            name: 'marks the unique helpers in any override and as this calls',
            code: [
                "Component.override('sw-category-detail', {",
                '    methods: {',
                '        extractSlotOverrides(origin, changes) {',
                "            return this.$super('extractSlotOverrides', origin, changes);",
                '        },',
                '        getCmsPageOverrides() {',
                '            this.deleteSpecifcKeys(this.cmsPage.sections);',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            output: [
                "Component.override('sw-category-detail', {",
                '    methods: {',
                `        ${todo(EXTRACT)}`,
                '        extractSlotOverrides(origin, changes) {',
                "            return this.$super('extractSlotOverrides', origin, changes);",
                '        },',
                `        ${todo(OVERRIDES)}`,
                '        getCmsPageOverrides() {',
                `            ${todo(DELETE)}`,
                '            this.deleteSpecifcKeys(this.cmsPage.sections);',
                '        },',
                '    },',
                '});',
            ].join('\n'),
            errors: [{ message: EXTRACT }, { message: OVERRIDES }, { message: DELETE }],
        },
        {
            name: 'marks $super of a helper when the target is unknown',
            code: "export default { methods: { resetCmsPageState() { this.$super('resetCmsPageState'); } } };",
            output: [todo(RESET), "export default { methods: { resetCmsPageState() { this.$super('resetCmsPageState'); } } };"].join('\n'),
            errors: [{ message: RESET }],
        },
    ],
});
