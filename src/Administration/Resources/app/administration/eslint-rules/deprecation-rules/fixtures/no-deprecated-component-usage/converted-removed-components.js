/**
 * @sw-package framework
 */

// The steps that carry the behaviour of sw-progress-bar, sw-select-number-field and
// sw-entity-many-to-many-select over to the elements the conversion produced.

const converted = (component) =>
    `<!-- TODO Codemod: Converted from ${component} - please check if everything works correctly -->`;

const OPTIONS_HEADLINE = '[mt-select] The "default" slot is removed. Pass its <option> elements as the "options" prop.';
const NUMBER_REVIEW =
    'sw-select-number-field turned the selected value into a number, mt-select passes it on as it is: check that the bound value is a number.';
const SAVE_REVIEW =
    'sw-entity-many-to-many-select saved every change of the assignment without local-mode, sw-entity-multi-select never does: save the entity collection together with its parent entity.';

const validTests = [
    {
        name: 'mt-progress-bar written by hand keeps its missing max-value',
        filename: 'test.html.twig',
        code: '<template><mt-progress-bar :model-value="progress" /></template>',
    },
    {
        name: 'mt-progress-bar converted from sw-progress-bar keeps its max-value',
        filename: 'test.html.twig',
        code: `<template>\n    ${converted('sw-progress-bar')}\n    <mt-progress-bar :model-value="progress" :max-value="total" />\n</template>`,
    },
    {
        name: 'sw-entity-multi-select written by hand is not checked for local-mode',
        filename: 'test.html.twig',
        code: '<template><sw-entity-multi-select v-model:entity-collection="tags" /></template>',
    },
];

const invalidTests = [
    {
        name: 'mt-progress-bar converted from sw-progress-bar gets the old max-value default',
        filename: 'test.html.twig',
        code: `<template>\n    ${converted('sw-progress-bar')}\n    <mt-progress-bar :model-value="progress" />\n</template>`,
        output: `<template>\n    ${converted('sw-progress-bar')}\n    <mt-progress-bar :model-value="progress" :max-value="100" />\n</template>`,
        errors: [
            {
                message:
                    '[mt-progress-bar] mt-progress-bar requires "max-value", which sw-progress-bar defaulted to 100. Add :max-value="100".',
            },
        ],
    },
    {
        // sw-select-field reports the options too, because its steps run on every mt-select; the steps of the
        // converted component report first, so their fix wins
        name: 'mt-select converted from sw-select-number-field keeps number values of its options',
        filename: 'test.html.twig',
        code: `<template>\n    ${converted('sw-select-number-field')}\n    <mt-select v-model="count" hide-clearable-button><option value="1">One</option><option value="-2.5">{{ $t('minus') }}</option></mt-select>\n</template>`,
        output: `<template>\n    ${converted('sw-select-number-field')}\n    <mt-select :options="[{ value: 1, label: 'One' }, { value: -2.5, label: $t('minus') }]" v-model="count" hide-clearable-button />\n</template>`,
        errors: [
            { message: OPTIONS_HEADLINE },
            { message: OPTIONS_HEADLINE },
        ],
    },
    {
        name: 'mt-select converted from sw-select-number-field moves a bound value with a review comment',
        filename: 'test.html.twig',
        code: `<template>\n    ${converted('sw-select-number-field')}\n    <mt-select v-model="count" hide-clearable-button><option :value="limit">Limit</option></mt-select>\n</template>`,
        output: `<template>\n    ${converted('sw-select-number-field')}\n    <!-- TODO Codemod: ${OPTIONS_HEADLINE} ${NUMBER_REVIEW} -->\n    <mt-select :options="[{ value: limit, label: 'Limit' }]" v-model="count" hide-clearable-button />\n</template>`,
        errors: [
            { message: `${OPTIONS_HEADLINE}\n${NUMBER_REVIEW}` },
            {
                message:
                    /^\[mt-select\] The "default" slot is removed\.[^\n]*\nsw-select-field passed the selected value on as a string/,
            },
        ],
    },
    {
        name: 'mt-select converted from sw-select-number-field gets the clear button hidden and the value renamed',
        filename: 'test.html.twig',
        code: `<template>\n    ${converted('sw-select-number-field')}\n    <mt-select :value="count" />\n</template>`,
        output: `<template>\n    ${converted('sw-select-number-field')}\n    <mt-select :model-value="count" hide-clearable-button />\n</template>`,
        errors: [
            {
                message:
                    '[mt-select] mt-select shows a clear button by default, which sw-select-number-field never had. Add "hide-clearable-button" to keep the field unclearable.',
            },
            { message: '[mt-select] The "value" API is deprecated. Use "model-value" instead.' },
            { message: '[mt-select] The "value" API is deprecated. Use "model-value" instead.' },
        ],
    },
    {
        name: 'sw-entity-multi-select converted with local-mode loses the prop without a review',
        filename: 'test.html.twig',
        code: `<template>\n    ${converted('sw-entity-many-to-many-select')}\n    <sw-entity-multi-select v-model:entity-collection="tags" local-mode />\n</template>`,
        output: `<template>\n    ${converted('sw-entity-many-to-many-select')}\n    <sw-entity-multi-select v-model:entity-collection="tags" />\n</template>`,
        errors: [{ message: '[sw-entity-multi-select] The "local-mode" API is deprecated.' }],
    },
    {
        name: 'sw-entity-multi-select converted with a bound local-mode loses the prop with a review comment',
        filename: 'test.html.twig',
        code: `<template>\n    ${converted('sw-entity-many-to-many-select')}\n    <sw-entity-multi-select v-model:entity-collection="tags" :local-mode="isNew" />\n</template>`,
        output: `<template>\n    ${converted('sw-entity-many-to-many-select')}\n    <!-- TODO Codemod: [sw-entity-multi-select] The "local-mode" prop is removed. ${SAVE_REVIEW} -->\n    <sw-entity-multi-select v-model:entity-collection="tags" />\n</template>`,
        errors: [{ message: `[sw-entity-multi-select] The "local-mode" prop is removed.\n${SAVE_REVIEW}` }],
    },
];

module.exports = {
    valid: validTests,
    invalid: invalidTests,
};
