/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-product-advanced-mode');
const data = require('./no-product-advanced-mode.data');

const member = (name) => data.members.find((entry) => entry.name === name).message;
const COMPONENT = data.components['sw-product-settings-mode'];
const LOADING = data.store.loadingMessage;
const scriptTodo = (text) => `// TODO Codemod(6.8, product-advanced-mode): ${text}`;
const twigTodo = (text) => `{# TODO Codemod(6.8, product-advanced-mode): ${text} #}`;

const invalidScript = (name, code, messages) => ({
    name,
    code,
    output: [...messages.map(scriptTodo), code].join('\n'),
    errors: messages.map((message) => ({ message })),
});

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-product-advanced-mode (script)', rule, {
    valid: [
        { name: 'ignores showModeSetting outside the product components', code: "Component.override('acme-form', { computed: { a() { return this.showModeSetting; } } });" },
        { name: 'ignores onChangeSetting of other components', code: "Component.override('sw-settings-search', { methods: { onChangeSetting() {} } });" },
        { name: 'ignores the modeSettings that stay', code: "const settings = Shopware.Store.get('swProductDetail').modeSettings;" },
        { name: 'ignores other loading keys', code: "Shopware.Store.get('swProductDetail').setLoading(['product', true]);\nconst busy = this.loading.product;" },
        { name: 'ignores showModeSetting of other stores', code: "const show = Shopware.Store.get('acmeStore').showModeSetting;" },
    ],
    invalid: [
        {
            name: 'marks the members of sw-product-detail',
            code: [
                "Component.override('sw-product-detail', {",
                '    computed: {',
                '        getModeSettingGeneralTab() {',
                "            return [...this.$super('getModeSettingGeneralTab'), acmeCard];",
                '        },',
                '    },',
                '});',
            ].join('\n'),
            output: [
                "Component.override('sw-product-detail', {",
                '    computed: {',
                `        ${scriptTodo(member('getModeSettingGeneralTab'))}`,
                '        getModeSettingGeneralTab() {',
                "            return [...this.$super('getModeSettingGeneralTab'), acmeCard];",
                '        },',
                '    },',
                '});',
            ].join('\n'),
            errors: [{ message: member('getModeSettingGeneralTab') }],
        },
        invalidScript(
            'marks showModeSetting in overrides of the forms',
            "Component.override('sw-product-price-form', { computed: { a() { return this.showModeSetting; } } });",
            [member('showModeSetting')],
        ),
        invalidScript('marks the store getters', "const show = Shopware.Store.get('swProductDetail').showModeSetting;", [member('showModeSetting')]),
        invalidScript('marks unique store members on any object', 'store.advancedModeSetting = setting;', [member('advancedModeSetting')]),
        invalidScript('marks the advancedMode loading state', "Shopware.Store.get('swProductDetail').setLoading(['advancedMode', true]);", [LOADING]),
        invalidScript('marks the advancedMode loading flag', 'const busy = this.loading.advancedMode;', [LOADING]),
        invalidScript('marks the user config key', "const key = 'mode.setting.advancedModeSettings';", [data.literals[0].message]),
        invalidScript('marks the component', "Component.extend('acme-mode', 'sw-product-settings-mode', {});", [COMPONENT]),
    ],
});

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('no-product-advanced-mode (template)', rule, {
    valid: [
        {
            name: 'ignores showModeSetting in templates of other components',
            filename: '/plugin/src/acme-form/acme-form.html.twig',
            code: '<template>\n    <div v-if="showModeSetting" />\n</template>',
        },
    ],
    invalid: [
        {
            name: 'marks showModeSetting in overrides of the forms',
            filename: '/plugin/src/sw-product-deliverability-form/sw-product-deliverability-form.html.twig',
            code: '<template>\n    <div v-if="showModeSetting" />\n</template>',
            output: ['<template>', `    ${twigTodo(member('showModeSetting'))}`, '    <div v-if="showModeSetting" />', '</template>'].join('\n'),
            errors: [{ message: member('showModeSetting') }],
        },
        {
            name: 'marks the tag of sw-product-settings-mode',
            filename: '/plugin/src/acme-detail/acme-detail.html.twig',
            code: '<template>\n    <sw-product-settings-mode :mode-settings="settings" />\n</template>',
            output: ['<template>', `    ${twigTodo(COMPONENT)}`, '    <sw-product-settings-mode :mode-settings="settings" />', '</template>'].join('\n'),
            errors: [{ message: COMPONENT }],
        },
    ],
});
