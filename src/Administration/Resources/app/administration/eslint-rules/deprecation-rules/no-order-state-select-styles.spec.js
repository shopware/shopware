/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-order-state-select-styles');

const tester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const template = (...lines) => ['<template>', ...lines, '</template>'].join('\n');
const HINT = 'sw-order-state-select-v2 no longer has background-style and rounded-style in 6.8. ' +
    'Pass the technical name of the current state as state-name, e.g. :state-name="order.stateMachineState.technicalName".';

tester.run('no-order-state-select-styles', rule, {
    valid: [
        {
            name: 'ignores the select with state-name',
            filename: 'test.html.twig',
            code: template('<sw-order-state-select-v2 :state-name="order.stateMachineState.technicalName" />'),
        },
        {
            name: 'ignores the props on other components',
            filename: 'test.html.twig',
            code: template('<acme-select :background-style="style" rounded-style />'),
        },
    ],
    invalid: [
        {
            name: 'derives state-name from the placeholder',
            filename: 'test.html.twig',
            code: template(
                '<sw-order-state-select-v2 :placeholder="transaction.stateMachineState.translated.name" :background-style="paymentStyle" rounded-style />',
            ),
            output: template(
                '<sw-order-state-select-v2 :placeholder="transaction.stateMachineState.translated.name" :state-name="transaction.stateMachineState.technicalName" />',
            ),
            errors: [{ messageId: 'replace' }],
        },
        {
            name: 'derives state-name from the background style helper core used',
            filename: 'test.html.twig',
            code: template('<sw-order-state-select-v2 :background-style="backgroundStyle(\'order_delivery\')" />'),
            output: template('<sw-order-state-select-v2 :state-name="delivery.stateMachineState.technicalName" />'),
            errors: [{ messageId: 'replace' }],
        },
        {
            name: 'only removes the props when state-name is set',
            filename: 'test.html.twig',
            code: template('<sw-order-state-select-v2 state-name="open" :rounded-style="true" />'),
            output: template('<sw-order-state-select-v2 state-name="open" />'),
            errors: [{ messageId: 'replace' }],
        },
        {
            name: 'removes the props and comments a select whose state it cannot derive',
            filename: 'test.html.twig',
            code: template('    <sw-order-state-select-v2 :background-style="customStyle" />'),
            output: template(`    <!-- TODO Codemod: ${HINT} -->`, '    <sw-order-state-select-v2 />'),
            errors: [{ messageId: 'todo' }, { messageId: 'replace' }],
        },
    ],
});
