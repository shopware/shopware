/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-unused-order-components');
const data = require('./no-unused-order-components.data');

const CREATE_BASE = data.components['sw-order-create-base'];
const USER_CARD = data.components['sw-order-user-card'];
const scriptTodo = (text) => `// TODO Codemod(6.8, order-unused-components): ${text}`;
const twigTodo = (text) => `{# TODO Codemod(6.8, order-unused-components): ${text} #}`;

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-unused-order-components (script)', rule, {
    valid: [
        { name: 'ignores the order create page', code: "Component.override('sw-order-create', {});" },
        { name: 'ignores the successors', code: "Component.override('sw-order-state-history-modal', {});\nComponent.override('sw-order-create-details', {});" },
    ],
    invalid: [
        {
            name: 'marks extensions of a removed component',
            code: "Component.extend('acme-create-base', 'sw-order-create-base', {});",
            output: [scriptTodo(CREATE_BASE), "Component.extend('acme-create-base', 'sw-order-create-base', {});"].join('\n'),
            errors: [{ message: CREATE_BASE }],
        },
    ],
});

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('no-unused-order-components (template)', rule, {
    valid: [
        {
            name: 'ignores the root block of sw-order-create',
            filename: '/plugin/src/sw-order-create/sw-order-create.html.twig',
            code: '<template>\n    {% block sw_order_create %}{% parent %}{% endblock %}\n</template>',
        },
    ],
    invalid: [
        {
            name: 'marks the tags',
            filename: '/plugin/src/acme-order/acme-order.html.twig',
            code: '<template>\n    <sw-order-user-card :current-order="order" />\n</template>',
            output: ['<template>', `    ${twigTodo(USER_CARD)}`, '    <sw-order-user-card :current-order="order" />', '</template>'].join('\n'),
            errors: [{ message: USER_CARD }],
        },
    ],
});
