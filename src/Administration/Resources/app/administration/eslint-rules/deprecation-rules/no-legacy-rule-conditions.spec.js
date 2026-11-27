/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-legacy-rule-conditions');
const data = require('./no-legacy-rule-conditions.data');

const PURCHASE_PRICE = data.components['sw-condition-line-item-purchase-price'];
const IN_CATEGORY = data.components['sw-condition-line-item-in-category'];
const IS_NET = data.components['sw-condition-is-net-select'];
const scriptTodo = (text) => `// TODO Codemod(6.8, rule-condition-components): ${text}`;
const twigTodo = (text) => `{# TODO Codemod(6.8, rule-condition-components): ${text} #}`;

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-legacy-rule-conditions (script)', rule, {
    valid: [
        { name: 'ignores the generic components', code: "Component.extend('acme-condition', 'sw-condition-generic-line-item', {});" },
        { name: 'ignores other component values', code: "ruleConditionDataProviderService.addCondition('acme', { component: 'sw-condition-generic' });" },
    ],
    invalid: [
        {
            name: 'marks extensions and overrides',
            code: [
                "Shopware.Component.extend('acme-purchase-price', 'sw-condition-line-item-purchase-price', {});",
                "Component.override('sw-condition-line-item-in-category', {});",
            ].join('\n'),
            output: [
                scriptTodo(PURCHASE_PRICE),
                "Shopware.Component.extend('acme-purchase-price', 'sw-condition-line-item-purchase-price', {});",
                scriptTodo(IN_CATEGORY),
                "Component.override('sw-condition-line-item-in-category', {});",
            ].join('\n'),
            errors: [{ message: PURCHASE_PRICE }, { message: IN_CATEGORY }],
        },
        {
            name: 'marks the component of a registered condition',
            code: [
                "ruleConditionDataProviderService.addCondition('acmeLineItemNet', {",
                "    component: 'sw-condition-is-net-select',",
                "    label: 'acme.condition',",
                '});',
            ].join('\n'),
            output: [
                "ruleConditionDataProviderService.addCondition('acmeLineItemNet', {",
                `    ${scriptTodo(IS_NET)}`,
                "    component: 'sw-condition-is-net-select',",
                "    label: 'acme.condition',",
                '});',
            ].join('\n'),
            errors: [{ message: IS_NET }],
        },
    ],
});

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('no-legacy-rule-conditions (template)', rule, {
    valid: [],
    invalid: [
        {
            name: 'marks the tags',
            filename: '/plugin/src/acme-condition/acme-condition.html.twig',
            code: '<template>\n    <sw-condition-is-net-select v-bind="{ operators, condition }" />\n</template>',
            output: ['<template>', `    ${twigTodo(IS_NET)}`, '    <sw-condition-is-net-select v-bind="{ operators, condition }" />', '</template>'].join('\n'),
            errors: [{ message: IS_NET }],
        },
    ],
});
