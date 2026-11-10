/**
 * @sw-package framework
 */

// sw-order-state-select-v2 lost background-style and rounded-style; the state color comes from state-name.

const HEADLINE =
    '[sw-order-state-select-v2] The "background-style" prop is removed. Pass the technical name of the current state as "state-name", e.g. :state-name="order.stateMachineState.technicalName".';
const COMMENT = `<!-- TODO Codemod: ${HEADLINE} -->`;

const validTests = [
    {
        name: 'sw-order-state-select-v2 with state-name is allowed',
        filename: 'test.html.twig',
        code: '<template><sw-order-state-select-v2 state-type="order" :state-name="order.stateMachineState.technicalName" /></template>',
    },
];

const invalidTests = [
    {
        name: 'background-style becomes the state-name of the state the placeholder names',
        filename: 'test.html.twig',
        code: `<template>
    <sw-order-state-select-v2
        state-type="order_transaction"
        :placeholder="transaction.stateMachineState.translated.name"
        :background-style="backgroundStyle('order_transaction')"
    />
</template>`,
        output: `<template>
    ${COMMENT}
    <sw-order-state-select-v2
        state-type="order_transaction"
        :placeholder="transaction.stateMachineState.translated.name"
        :state-name="transaction.stateMachineState.technicalName"
    />
</template>`,
        errors: [{ message: HEADLINE }],
    },
    {
        name: 'background-style of a core helper becomes the state-name it stood for',
        filename: 'test.html.twig',
        code: `<template>\n    <sw-order-state-select-v2 :state-type="entityName" :background-style="stateSelectBackgroundStyle" />\n</template>`,
        output: `<template>\n    ${COMMENT}\n    <sw-order-state-select-v2 :state-type="entityName" :state-name="entity.stateMachineState.technicalName" />\n</template>`,
        errors: [{ message: HEADLINE }],
    },
    {
        name: 'background-style is only removed next to a state-name',
        filename: 'test.html.twig',
        code: `<template>\n    <sw-order-state-select-v2 :state-name="stateName" :background-style="style" />\n</template>`,
        output: `<template>\n    ${COMMENT}\n    <sw-order-state-select-v2 :state-name="stateName" />\n</template>`,
        errors: [{ message: HEADLINE }],
    },
    {
        name: 'background-style without a state to derive is removed with a review comment',
        filename: 'test.html.twig',
        code: `<template>\n    <sw-order-state-select-v2 :placeholder="$t('x')" background-style="color: red" />\n</template>`,
        output: `<template>\n    ${COMMENT}\n    <sw-order-state-select-v2 :placeholder="$t('x')" />\n</template>`,
        errors: [{ message: HEADLINE }],
    },
    {
        name: 'background-style is not fixed at the safe level',
        filename: 'test.html.twig',
        settings: { 'sw-deprecation-rules': { level: 'safe' } },
        code: '<template><sw-order-state-select-v2 :state-name="stateName" :background-style="style" /></template>',
        output: null,
        errors: [{ message: HEADLINE }],
    },
    {
        name: 'rounded-style is removed',
        filename: 'test.html.twig',
        code: '<template><sw-order-state-select-v2 state-type="order" rounded-style /></template>',
        output: '<template><sw-order-state-select-v2 state-type="order" /></template>',
        errors: [{ message: '[sw-order-state-select-v2] The "rounded-style" API is deprecated.' }],
    },
];

module.exports = {
    valid: validTests,
    invalid: invalidTests,
};
