/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-agentic-commerce-ui');
const data = require('./no-agentic-commerce-ui.data');

const member = (name) => data.members.find((entry) => entry.name === name).message;
const TRACKING = data.components['sw-agentic-commerce-tracking-config'];
const DEFAULTS = data.globals[0].message;
const ROUTE = data.literals[0].message;
const scriptTodo = (text) => `// TODO Codemod(6.8, agentic-commerce-ui): ${text}`;
const twigTodo = (text) => `{# TODO Codemod(6.8, agentic-commerce-ui): ${text} #}`;

const invalidScript = (name, code, messages) => ({
    name,
    code,
    output: [...messages.map(scriptTodo), code].join('\n'),
    errors: messages.map((message) => ({ message })),
});

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-agentic-commerce-ui (script)', rule, {
    valid: [
        { name: 'ignores the agentic files views, which stay', code: "Component.override('sw-sales-channel-detail-agentic-files', {});\nComponent.override('sw-sales-channel-detail-agentic-file', {});" },
        { name: 'ignores the agentic files routes', code: "this.$router.push({ name: 'sw.sales.channel.detail.agenticFiles' });" },
        { name: 'ignores isAgenticCommerce outside the sales channel components', code: "Component.override('acme-channel', { computed: { a() { return this.isAgenticCommerce; } } });" },
        { name: 'ignores the other sales channel type ids', code: 'const id = Shopware.Defaults.productComparisonTypeId;' },
    ],
    invalid: [
        {
            name: 'marks the members of the sales channel components',
            code: [
                "Component.override('sw-sales-channel-detail-base', {",
                '    methods: {',
                '        getAgenticCommerceExportCardTitle(element) {',
                "            return this.isAgenticCommerce ? element.title : '';",
                '        },',
                '    },',
                '});',
            ].join('\n'),
            output: [
                "Component.override('sw-sales-channel-detail-base', {",
                '    methods: {',
                `        ${scriptTodo(member('getAgenticCommerceExportCardTitle'))}`,
                '        getAgenticCommerceExportCardTitle(element) {',
                `            ${scriptTodo(member('isAgenticCommerce'))}`,
                "            return this.isAgenticCommerce ? element.title : '';",
                '        },',
                '    },',
                '});',
            ].join('\n'),
            errors: [{ message: member('getAgenticCommerceExportCardTitle') }, { message: member('isAgenticCommerce') }],
        },
        invalidScript('marks the tracking config component', "Component.override('sw-agentic-commerce-tracking-config', {});", [TRACKING]),
        {
            name: 'marks the sales channel type id',
            code: 'const { Defaults } = Shopware;\nconst isAgentic = typeId === Defaults.agenticCommerceTypeId;',
            output: ['const { Defaults } = Shopware;', scriptTodo(DEFAULTS), 'const isAgentic = typeId === Defaults.agenticCommerceTypeId;'].join('\n'),
            errors: [{ message: DEFAULTS }],
        },
        invalidScript('marks the integration route', "this.$router.push({ name: 'sw.sales.channel.detail.agenticCommerceIntegration' });", [ROUTE]),
    ],
});

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('no-agentic-commerce-ui (template)', rule, {
    valid: [
        {
            name: 'ignores the agentic files tab',
            filename: '/plugin/src/sw-sales-channel-detail/sw-sales-channel-detail.html.twig',
            code: "<template>\n    <sw-tabs-item :route=\"{ name: 'sw.sales.channel.detail.agenticFiles' }\" />\n</template>",
        },
    ],
    invalid: [
        {
            name: 'marks members and tags in overrides',
            filename: '/plugin/src/sw-sales-channel-detail-base/sw-sales-channel-detail-base.html.twig',
            code: '<template>\n    <sw-agentic-commerce-tracking-config v-if="isAgenticCommerce" />\n</template>',
            output: ['<template>', `    ${twigTodo(TRACKING)}`, '    <sw-agentic-commerce-tracking-config v-if="isAgenticCommerce" />', '</template>'].join('\n'),
            errors: [{ message: TRACKING }, { message: member('isAgenticCommerce') }],
        },
    ],
});
