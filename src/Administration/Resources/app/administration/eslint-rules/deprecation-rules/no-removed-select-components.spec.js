/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-removed-select-components');

const tester = new RuleTester({
    languageOptions: {
        parser: require('vue-eslint-parser'),
        ecmaVersion: 2022,
        sourceType: 'module',
    },
});

const template = (...lines) => ['<template>', ...lines, '</template>'].join('\n');
const replace = { messageId: 'replace' };
const todo = { messageId: 'todo' };

const OPTIONS_HINT = 'sw-select-number-field is removed in 6.8. Pass the options to mt-select with the options prop, ' +
    'as objects with a number value and a label.';
const LOCAL_MODE_HINT = 'sw-entity-many-to-many-select is removed in 6.8. sw-entity-multi-select does not save the ' +
    'assignment on its own, save the entity collection when the parent entity is saved.';

tester.run('no-removed-select-components', rule, {
    valid: [
        {
            name: 'ignores the replacements without a TODO comment',
            filename: 'test.html.twig',
            code: template('<mt-select :options="[]" />', '<sw-entity-multi-select :entity-collection="tags" />'),
        },
    ],
    invalid: [
        {
            name: 'turns the option children of sw-select-number-field into options',
            filename: 'test.html.twig',
            code: template(
                '    <sw-select-number-field v-model:value="config.days" :label="$t(\'acme.days\')">',
                '        <option value="1">One day</option>',
                '        <option :value="7">{{ $tc(\'acme.week\') }}</option>',
                '    </sw-select-number-field>',
            ),
            output: template(
                '    <mt-select v-model:value="config.days" :label="$t(\'acme.days\')" ' +
                    ':options="[{ value: 1, label: \'One day\' }, { value: 7, label: $t(\'acme.week\') }]" />',
            ),
            errors: [replace],
        },
        {
            name: 'maps the options prop of sw-select-number-field',
            filename: 'test.html.twig',
            code: template('<sw-select-number-field :options="periods" v-model:value="period" />'),
            output: template('<mt-select value-property="id" label-property="name" :options="periods" v-model:value="period" />'),
            errors: [replace],
        },
        {
            name: 'renames sw-select-number-field and comments options it cannot convert',
            filename: 'test.html.twig',
            code: template(
                '    <sw-select-number-field v-model:value="days">',
                '        <option v-for="day in days" :value="day">{{ day }}</option>',
                '    </sw-select-number-field>',
            ),
            output: template(
                `    <!-- TODO Codemod: ${OPTIONS_HINT} -->`,
                '    <mt-select v-model:value="days">',
                '        <option v-for="day in days" :value="day">{{ day }}</option>',
                '    </mt-select>',
            ),
            errors: [todo, replace],
        },
        {
            name: 'replaces sw-entity-many-to-many-select in local mode',
            filename: 'test.html.twig',
            code: template('<sw-entity-many-to-many-select :local-mode="true" :entity-collection="product.tags" />'),
            output: template('<sw-entity-multi-select :entity-collection="product.tags" />'),
            errors: [replace],
        },
        {
            name: 'comments sw-entity-many-to-many-select without a static local mode',
            filename: 'test.html.twig',
            code: template(
                '    <sw-entity-many-to-many-select',
                '        :local-mode="product.isNew()"',
                '        :entity-collection="product.tags"',
                '    ></sw-entity-many-to-many-select>',
            ),
            output: template(
                `    <!-- TODO Codemod: ${LOCAL_MODE_HINT} -->`,
                '    <sw-entity-multi-select',
                '        :entity-collection="product.tags"',
                '    ></sw-entity-multi-select>',
            ),
            errors: [todo, replace],
        },
        {
            name: 'keeps reporting the TODO comment above a replaced select',
            filename: 'test.html.twig',
            code: template(`    <!-- TODO Codemod: ${LOCAL_MODE_HINT} -->`, '    <sw-entity-multi-select :entity-collection="tags" />'),
            output: null,
            errors: [todo],
        },
    ],
});
