/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-removed-module-members');
const data = require('./no-removed-module-members.data');

const message = (name, component) => data.members.find((entry) => entry.name === name && (!component || [entry.component].flat().includes(component))).message;
const scriptTodo = (text) => `// TODO Codemod(6.8, settings-product-promotion-members): ${text}`;
const twigTodo = (text) => `{# TODO Codemod(6.8, settings-product-promotion-members): ${text} #}`;

const invalidScript = (name, code, messages) => ({
    name,
    code,
    output: [...messages.map(scriptTodo), code].join('\n'),
    errors: messages.map((text) => ({ message: text })),
});

describe('no-removed-module-members data', () => {
    it('scopes generic names to their component', () => {
        const generic = ['isLoading', 'ruleFilter', 'ruleCriteria', 'repository', 'sortBy', 'getUserConfig', 'isSaveSuccessful', 'countryRepository'];

        expect(data.members.filter((entry) => generic.includes(entry.name)).every((entry) => !entry.unique && entry.component)).toBe(true);
    });
});

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-removed-module-members (script)', rule, {
    valid: [
        { name: 'ignores generic names outside the target', code: "Component.override('sw-product-list', { computed: { isLoading() { return this.isLoading; } } });" },
        { name: 'ignores ruleFilter of other components', code: "Component.override('sw-settings-tax-provider-detail', { computed: { ruleFilter() { return this.ruleFilter; } } });" },
        { name: 'ignores a computed sortBy of sw-settings-document-list', code: "Component.override('sw-settings-document-list', { computed: { sortBy() { return 'name'; } } });" },
        { name: 'ignores the replacements', code: 'this.buildSelectedSalesChannelsCollection();\nconst criteria = this.ruleFilterCriteria;' },
    ],
    invalid: [
        {
            name: 'marks generic members in overrides of their component',
            code: [
                "Component.override('sw-settings-customer-group-detail', {",
                '    methods: {',
                '        getSeoUrl(seoUrl) {',
                "            return this.$super('getSeoUrl', seoUrl);",
                '        },',
                '    },',
                '});',
            ].join('\n'),
            output: [
                "Component.override('sw-settings-customer-group-detail', {",
                '    methods: {',
                `        ${scriptTodo(message('getSeoUrl'))}`,
                '        getSeoUrl(seoUrl) {',
                "            return this.$super('getSeoUrl', seoUrl);",
                '        },',
                '    },',
                '});',
            ].join('\n'),
            errors: [{ message: message('getSeoUrl') }],
        },
        invalidScript(
            'marks isLoading of sw-product-packaging-form',
            "Component.override('sw-product-packaging-form', { methods: { a() { return this.isLoading; } } });",
            [message('isLoading')],
        ),
        invalidScript(
            'marks the duplicated rule criteria of the promotion components',
            "Component.override('sw-promotion-v2-settings-trigger', { computed: { ruleCriteria() { return null; } } });",
            [message('ruleCriteria')],
        ),
        invalidScript('marks distinctive names anywhere', 'const criteria = this.shippingRuleFilterCriteria;', [message('shippingRuleFilterCriteria')]),
        invalidScript('marks the rename banner user config key', "userConfigService.search(['settings.hideRenameBanner']);", [data.literals[0].message]),
        invalidScript(
            'marks removed components',
            "Component.override('sw-promotion-v2-empty-state-hero', {});",
            [data.components['sw-promotion-v2-empty-state-hero']],
        ),
    ],
});

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('no-removed-module-members (template)', rule, {
    valid: [
        {
            name: 'ignores generic names in templates of other components',
            filename: '/plugin/src/acme-list/acme-list.html.twig',
            code: '<template>\n    <sw-select-rule-create :rule-filter="ruleFilter" />\n</template>',
        },
    ],
    invalid: [
        {
            name: 'marks the rule criteria in overrides of the promotion components',
            filename: '/plugin/src/sw-promotion-v2-cart-condition-form/sw-promotion-v2-cart-condition-form.html.twig',
            code: '<template>\n    <sw-select-rule-create :rule-filter="ruleFilter" />\n</template>',
            output: ['<template>', `    ${twigTodo(message('ruleFilter'))}`, '    <sw-select-rule-create :rule-filter="ruleFilter" />', '</template>'].join('\n'),
            errors: [{ message: message('ruleFilter') }],
        },
        {
            name: 'marks the services banner tag',
            filename: '/plugin/src/acme-dashboard/acme-dashboard.html.twig',
            code: '<template>\n    <sw-settings-services-dashboard-banner />\n</template>',
            output: [
                '<template>',
                `    ${twigTodo(data.components['sw-settings-services-dashboard-banner'])}`,
                '    <sw-settings-services-dashboard-banner />',
                '</template>',
            ].join('\n'),
            errors: [{ message: data.components['sw-settings-services-dashboard-banner'] }],
        },
    ],
});
