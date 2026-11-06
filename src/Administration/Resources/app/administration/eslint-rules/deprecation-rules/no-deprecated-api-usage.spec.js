/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-deprecated-api-usage');

const scriptTester = new RuleTester({
    languageOptions: { ecmaVersion: 2022, sourceType: 'module' },
});

const templateTester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const PARTIAL = { 'sw-deprecation-rules': { level: 'partial' } };

scriptTester.run('no-deprecated-api-usage (script)', rule, {
    valid: [
        {
            name: 'allows the replacements',
            code: [
                'Shopware.Store.get("swOrderDetail").setLoading(["order", true]);',
                'const controller = new AbortController();',
                'this.mailService.sendMailTemplate(to, name, template, media, salesChannelId, false, [], { order });',
            ].join('\n'),
        },
        {
            name: 'ignores members of other components and own components',
            filename: '/plugin/src/extension/sw-product-list/index.js',
            code: [
                'export default { computed: { busy() { return this.isLoading; } } };',
                'Shopware.Component.register("acme-list", { methods: { f() { return this.dateFilter(1); } } });',
            ].join('\n'),
        },
        {
            name: 'ignores an override that defines the filter computed itself',
            code: [
                'Shopware.Component.override("sw-order-list", {',
                '    computed: { dateFilter() { return Shopware.Filter.getByName("date"); } },',
                '    methods: { f(value) { return this.dateFilter(value); } },',
                '});',
            ].join('\n'),
        },
    ],
    invalid: [
        {
            name: 'reports a global member and leaves it at the safe level',
            code: 'Shopware.State.commit("acme/setFoo", 1);',
            errors: [{ message: /The "Shopware\.State" API is deprecated\. Use Shopware\.Store instead\./ }],
        },
        {
            name: 'writes a review comment above the line at the partial level',
            code: 'const { State } = Shopware;',
            output: '/* TODO Codemod: The "Shopware.State" API is deprecated. Use Shopware.Store instead. */\nconst { State } = Shopware;',
            settings: PARTIAL,
            errors: 1,
        },
        {
            name: 'rewrites a removed filter computed in an override',
            code: 'Shopware.Component.override("sw-order-list", { methods: { f(v) { return this.dateFilter(v) + this.$super("dateFilter")(v); } } });',
            output: 'Shopware.Component.override("sw-order-list", { methods: { f(v) { return Shopware.Filter.getByName(\'date\')(v) + Shopware.Filter.getByName(\'date\')(v); } } });',
            errors: 2,
        },
        {
            name: 'reports removed members of an override file named after the component',
            filename: '/plugin/src/extension/sw-order-detail/index.js',
            code: 'export default { inject: ["swOrderDetailOnLoadingChange"], computed: { busy() { return this.isLoading; } } };',
            errors: [
                { message: /"swOrderDetailOnLoadingChange" API is deprecated/ },
                { message: /"sw-order-detail\.isLoading" API is deprecated/ },
            ],
        },
        {
            name: 'reports a member an override defines',
            code: 'Shopware.Component.override("sw-product-detail", { computed: { productStates() { return []; } } });',
            errors: [{ message: /"sw-product-detail\.productStates" API is deprecated\. Use productType instead\./ }],
        },
        {
            name: 'reports overrides and references of removed components, but not the name inside the call',
            code: [
                'Shopware.Component.override("sw-help-sidebar", {});',
                'const condition = { component: "sw-condition-is-net-select" };',
            ].join('\n'),
            errors: [
                { message: /"sw-help-sidebar" API is deprecated\. Use sw-help-center-v2 instead\./ },
                { message: /"sw-condition-is-net-select" API is deprecated/ },
            ],
        },
        {
            name: 'reports imports, object keys, service methods and store members',
            code: [
                'import { CancelToken } from "axios";',
                'httpClient.get("/foo", { cancelToken: source.token });',
                'this.mailService.sendMailTemplate(to, name, template);',
                'Shopware.Service("systemConfigApiService").getConfig("Acme.config");',
                'const states = Shopware.Store.get("swProductDetail").productStates;',
            ].join('\n'),
            errors: 5,
        },
        {
            name: 'reports a build config file',
            filename: '/plugin/src/Resources/app/administration/src/vite.config.mts',
            code: 'export default {};',
            errors: [{ message: /vite/ }],
        },
    ],
});

templateTester.run('no-deprecated-api-usage (template)', rule, {
    valid: [
        {
            name: 'ignores tags a component migration converts',
            filename: 'test.html.twig',
            code: '<template><sw-text-editor v-model:value="text" /></template>',
        },
        {
            name: 'ignores slot props of the same name as a removed member',
            filename: '/plugin/src/extension/sw-order-detail/sw-order-detail.html.twig',
            code: '<template><sw-card #default="{ isLoading }"><p v-if="isLoading" /></sw-card></template>',
        },
    ],
    invalid: [
        {
            name: 'comments a removed component member in a Twig interpolation at the partial level',
            filename: '/plugin/src/extension/sw-review-list/sw-review-list.html.twig',
            code: ['<template>', '    <span>{{ dateFilter(item.createdAt) }}</span>', '</template>'].join('\n'),
            output: [
                '<template>',
                '    <!-- TODO Codemod: The "sw-review-list.dateFilter" API is deprecated. Use Shopware.Filter.getByName(\'date\') instead. -->',
                '    <span>{{ dateFilter(item.createdAt) }}</span>',
                '</template>',
            ].join('\n'),
            settings: PARTIAL,
            errors: 1,
        },
        {
            name: 'reports tags of removed components without a component migration',
            filename: 'test.html.twig',
            code: '<template><sw-help-sidebar /></template>',
            errors: [{ message: /"sw-help-sidebar" API is deprecated/ }],
        },
    ],
});
