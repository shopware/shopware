/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-order-state-select-v2-styles');

const ROUNDED = 'rounded-style of sw-order-state-select-v2 has no effect and is removed in 6.8.';
const STATE_NAME = 'background-style of sw-order-state-select-v2 is removed in 6.8. Use state-name with the technical name of the state instead.';
const BACKGROUND_TODO = 'background-style of sw-order-state-select-v2 has no effect and is removed in 6.8. Pass the technical name '
    + 'of the current state as state-name instead, e.g. :state-name="order.stateMachineState.technicalName".';

const GENERAL_INFO = '/plugin/src/extension/sw-order-general-info/sw-order-general-info.html.twig';
const STATE_CARD = '/plugin/src/extension/sw-order-details-state-card/sw-order-details-state-card.html.twig';

const twigTodo = `{# TODO Codemod(6.8, order-state-select-props): ${BACKGROUND_TODO} #}`;
const template = (...lines) => ['<template>', ...lines, '</template>'].join('\n');

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('no-order-state-select-v2-styles', rule, {
    valid: [
        {
            name: 'a second run leaves the converted select alone',
            filename: GENERAL_INFO,
            code: template(
                '    <sw-order-state-select-v2 state-type="order_transaction"',
                '        :state-name="transaction.stateMachineState.technicalName" :transition-options="paymentStateOptions" />',
            ),
        },
        {
            name: 'ignores the props on other components',
            filename: GENERAL_INFO,
            code: template('    <acme-select rounded-style :background-style="backgroundStyle(\'order\')" />'),
        },
    ],
    invalid: [
        {
            name: 'replaces the background-style helper of sw-order-general-info and removes rounded-style',
            filename: GENERAL_INFO,
            code: template(
                '    <sw-order-state-select-v2 state-type="order_transaction" rounded-style',
                "        :background-style=\"backgroundStyle('order_transaction')\" :transition-options=\"paymentStateOptions\" />",
                "    <sw-order-state-select-v2 state-type=\"order_delivery\" :background-style=\"backgroundStyle('order_delivery')\" :rounded-style=\"true\" />",
                "    <sw-order-state-select-v2 state-type=\"order\" :background-style=\"backgroundStyle('order')\" />",
            ),
            output: template(
                '    <sw-order-state-select-v2 state-type="order_transaction"',
                '        :state-name="transaction.stateMachineState.technicalName" :transition-options="paymentStateOptions" />',
                '    <sw-order-state-select-v2 state-type="order_delivery" :state-name="delivery.stateMachineState.technicalName" />',
                '    <sw-order-state-select-v2 state-type="order" :state-name="order.stateMachineState.technicalName" />',
            ),
            errors: [
                { message: `${ROUNDED} ${STATE_NAME}` },
                { message: `${ROUNDED} ${STATE_NAME}` },
                { message: STATE_NAME },
            ],
        },
        {
            name: 'replaces stateSelectBackgroundStyle of sw-order-details-state-card',
            filename: STATE_CARD,
            code: template('    <sw-order-state-select-v2 :state-type="entityName" :background-style="stateSelectBackgroundStyle" />'),
            output: template('    <sw-order-state-select-v2 :state-type="entityName" :state-name="entity.stateMachineState.technicalName" />'),
            errors: [{ message: STATE_NAME }],
        },
        {
            name: 'removes background-style when state-name is set',
            filename: 'test.html.twig',
            code: template('    <sw-order-state-select-v2 state-type="order" :state-name="stateName" background-style="sw-order-state__bg-neutral-select" />'),
            output: template('    <sw-order-state-select-v2 state-type="order" :state-name="stateName" />'),
            errors: [{ message: STATE_NAME }],
        },
        {
            name: 'marks the helper outside of an override of sw-order-general-info and still removes rounded-style',
            filename: '/plugin/src/acme-order-card/acme-order-card.html.twig',
            code: template("    <sw-order-state-select-v2 state-type=\"order\" rounded-style :background-style=\"backgroundStyle('order')\" />"),
            output: template(
                `    ${twigTodo}`,
                "    <sw-order-state-select-v2 state-type=\"order\" :background-style=\"backgroundStyle('order')\" />",
            ),
            errors: [{ message: BACKGROUND_TODO }, { message: ROUNDED }],
        },
        {
            name: 'marks unknown helper arguments in .vue files',
            filename: 'test.vue',
            code: template('    <sw-order-state-select-v2 :state-type="type" :background-style="backgroundStyle(type)" />'),
            output: template(
                `    <!-- TODO Codemod(6.8, order-state-select-props): ${BACKGROUND_TODO} -->`,
                '    <sw-order-state-select-v2 :state-type="type" :background-style="backgroundStyle(type)" />',
            ),
            errors: [{ message: BACKGROUND_TODO }],
        },
    ],
});
