/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-unused-order-components');
const data = require('./no-unused-order-components.data');

const HISTORY_CARD = data.components['sw-order-state-history-card'];
const CREATE_BASE = data.components['sw-order-create-base'];
const USER_CARD = data.components['sw-order-user-card'];
const scriptTodo = (text) => `// TODO Codemod(6.8, order-unused-components): ${text}`;
const twigTodo = (text) => `{# TODO Codemod(6.8, order-unused-components): ${text} #}`;

describe('no-unused-order-components data', () => {
    it('covers the eight removed components', () => {
        expect(Object.keys(data.components)).toEqual([
            'sw-order-create-base',
            'sw-order-create-details-header',
            'sw-order-create-details-body',
            'sw-order-create-details-footer',
            'sw-order-create-address-modal',
            'sw-order-state-history-card',
            'sw-order-state-history-card-entry',
            'sw-order-user-card',
        ]);
    });
});

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-unused-order-components (script)', rule, {
    valid: [
        { name: 'ignores the order create page', code: "Component.override('sw-order-create', {});" },
        { name: 'ignores the successors', code: "Component.override('sw-order-state-history-modal', {});\nComponent.override('sw-order-create-details', {});" },
    ],
    invalid: [
        {
            name: 'marks overrides, extensions and builds',
            code: [
                "Shopware.Component.override('sw-order-state-history-card', {});",
                "Component.extend('acme-create-base', 'sw-order-create-base', {});",
                "Component.build('sw-order-user-card');",
            ].join('\n'),
            output: [
                scriptTodo(HISTORY_CARD),
                "Shopware.Component.override('sw-order-state-history-card', {});",
                scriptTodo(CREATE_BASE),
                "Component.extend('acme-create-base', 'sw-order-create-base', {});",
                scriptTodo(USER_CARD),
                "Component.build('sw-order-user-card');",
            ].join('\n'),
            errors: [{ message: HISTORY_CARD }, { message: CREATE_BASE }, { message: USER_CARD }],
        },
        {
            name: 'reports a marked override without a fix',
            code: [scriptTodo(HISTORY_CARD), "Component.override('sw-order-state-history-card', {});"].join('\n'),
            output: null,
            errors: [{ message: HISTORY_CARD }],
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
