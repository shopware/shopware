const RuleTester = require('eslint').RuleTester;
const rule = require('./no-deprecated-template-events');

const tester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2015,
    },
});

tester.run('no-deprecated-template-events', rule, {
    valid: [
        {
            name: 'allows already migrated event',
            filename: 'test.html.twig',
            code: '<template><sw-text-field @update:value="onInput" /></template>',
        },
        {
            name: 'allows unrelated component',
            filename: 'test.html.twig',
            code: '<template><div @input="onInput" /></template>',
        },
        {
            name: 'ignores dynamic event arguments',
            filename: 'test.html.twig',
            code: '<template><sw-text-field v-on:[eventName]="onInput" /></template>',
        },
        {
            name: 'allows change on sw-price-field, which has no replacement event',
            filename: 'test.html.twig',
            code: '<template><sw-price-field v-on:change="onChange" /></template>',
        },
        {
            name: 'allows change on sw-select-field, which mt-select still emits',
            filename: 'test.html.twig',
            code: '<template><sw-select-field @change="onChange" /></template>',
        },
    ],
    invalid: [
        {
            name: 'migrates shorthand event listener',
            filename: 'test.html.twig',
            code: '<template><sw-text-field @input="onInput" /></template>',
            output: '<template><sw-text-field @update:value="onInput" /></template>',
            errors: [
                {
                    message: /Removed in Shopware 6\.6\.0/,
                },
            ],
        },
        {
            name: 'migrates v-on event listener',
            filename: 'test.html.twig',
            code: '<template><sw-single-select v-on:change="onChange" /></template>',
            output: '<template><sw-single-select v-on:update:value="onChange" /></template>',
            errors: [
                {
                    message: /Use "update:value" instead/,
                },
            ],
        },
        {
            name: 'migrates sw-entity-multi-id-select change to the emitted update:value',
            filename: 'test.html.twig',
            code: '<template><sw-entity-multi-id-select @change="onChange" /></template>',
            output: '<template><sw-entity-multi-id-select @update:value="onChange" /></template>',
            errors: [
                {
                    message: /Use "update:value" instead/,
                },
            ],
        },
        {
            name: 'migrates sw-radio-panel input to the emitted update:modelValue',
            filename: 'test.html.twig',
            code: '<template><sw-radio-panel @input="onSelect" /></template>',
            output: '<template><sw-radio-panel @update:modelValue="onSelect" /></template>',
            errors: [
                {
                    message: /Use "update:modelValue" instead/,
                },
            ],
        },
        {
            name: 'migrates sw-textarea-field input to the emitted update:model-value',
            filename: 'test.html.twig',
            code: '<template><sw-textarea-field @input="onInput" /></template>',
            output: '<template><sw-textarea-field @update:model-value="onInput" /></template>',
            errors: [
                {
                    message: /Use "update:model-value" instead/,
                },
            ],
        },
        {
            name: 'can disable fixer',
            filename: 'test.html.twig',
            options: ['disableFix'],
            code: '<template><sw-media-library @media-selection-change="onChange" /></template>',
            output: null,
            errors: [
                {
                    message: /Use "update:selection" instead/,
                },
            ],
        },
        {
            name: 'does not fix when object v-on can hide deprecated listeners',
            filename: 'test.html.twig',
            code: '<template><sw-text-field v-on="listeners" @input="onInput" /></template>',
            output: null,
            errors: [
                {
                    message: /Object v-on can hide the replacement event/,
                },
            ],
        },
    ],
});
