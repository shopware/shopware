/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-removed-form-components');

const SELECT = 'sw-select-number-field is removed. Use mt-select instead.';
const MANY_TO_MANY = 'sw-entity-many-to-many-select is removed. Use sw-entity-multi-select instead.';
const MANY_TO_MANY_MANUAL = 'sw-entity-many-to-many-select is removed. Use sw-entity-multi-select, which does not save '
    + 'the association itself: handle @update:entity-collection and save the parent entity.';
const HELP_SIDEBAR = 'sw-help-sidebar is removed without replacement. The help center content is part of sw-help-center-v2.';
const HELP_SIDEBAR_OVERRIDE = 'sw-help-sidebar is removed. Move this override to sw-help-center-v2, which has the same Twig block names.';
const MANY_TO_MANY_OVERRIDE = 'sw-entity-many-to-many-select is removed. Move this override to sw-entity-multi-select.';

function selectManual(reason) {
    return 'sw-select-number-field is removed. Use mt-select with v-model and :options="[{ value: 1, label: \'…\' }]" '
        + `with numeric values. Not converted automatically because of ${reason}.`;
}

const scriptTodo = (message) => `// TODO Codemod(6.8, removed-form-components): ${message}`;
const twigTodo = (message) => `{# TODO Codemod(6.8, removed-form-components): ${message} #}`;
const template = (...lines) => ['<template>', ...lines, '</template>'].join('\n');

function manualSelectCase(name, reason, ...lines) {
    const [first, ...rest] = lines;

    return {
        name,
        filename: 'test.html.twig',
        code: template(...lines),
        output: template(`    ${twigTodo(selectManual(reason))}`, first, ...rest),
        errors: [{ message: selectManual(reason) }],
    };
}

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-removed-form-components (script)', rule, {
    valid: [
        { name: 'ignores other overrides', code: "Component.override('sw-help-center-v2', {});" },
        { name: 'ignores registrations of other names', code: "Component.register('acme-help-sidebar', {});" },
    ],
    invalid: [
        {
            name: 'marks overrides and extensions of the removed components',
            code: [
                "Component.override('sw-help-sidebar', { template });",
                "Shopware.Component.extend('acme-select', 'sw-entity-many-to-many-select', {});",
            ].join('\n'),
            output: [
                scriptTodo(HELP_SIDEBAR_OVERRIDE),
                "Component.override('sw-help-sidebar', { template });",
                scriptTodo(MANY_TO_MANY_OVERRIDE),
                "Shopware.Component.extend('acme-select', 'sw-entity-many-to-many-select', {});",
            ].join('\n'),
            errors: [{ message: HELP_SIDEBAR_OVERRIDE }, { message: MANY_TO_MANY_OVERRIDE }],
        },
        {
            name: 'reports a marked override without a fix',
            code: [scriptTodo(HELP_SIDEBAR_OVERRIDE), "Component.override('sw-help-sidebar', {});"].join('\n'),
            output: null,
            errors: [{ message: HELP_SIDEBAR_OVERRIDE }],
        },
    ],
});

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('no-removed-form-components (template)', rule, {
    valid: [
        {
            name: 'a second run leaves the converted components alone',
            filename: 'test.html.twig',
            code: template(
                "    <mt-select v-model=\"columns\" :label=\"$t('acme.columns')\" :options=\"[{ value: 1, label: '1' }, { value: 2, label: '2' }]\" />",
                '    <sw-entity-multi-select :entity-collection="product.tags" @update:entity-collection="setTags" />',
            ),
        },
    ],
    invalid: [
        {
            name: 'converts static options to mt-select options with numeric values',
            filename: 'test.html.twig',
            code: template(
                "    <sw-select-number-field v-model:value=\"columns\" :label=\"$t('acme.columns')\">",
                '        <option value="1">1</option>',
                "        <option value=\"2\">{{ $t('acme.twoColumns') }}</option>",
                '    </sw-select-number-field>',
            ),
            output: template(
                "    <mt-select v-model=\"columns\" :label=\"$t('acme.columns')\" :options=\"[{ value: 1, label: '1' }, { value: 2, label: $t('acme.twoColumns') }]\" />",
            ),
            errors: [{ message: SELECT }],
        },
        {
            name: 'keeps an options prop and keeps the line breaks of the start tag',
            filename: 'test.html.twig',
            code: template(
                '    <sw-select-number-field',
                '        :value="columns"',
                '        :options="columnOptions"',
                '        @update:value="onColumns"',
                '    />',
            ),
            output: template(
                '    <mt-select',
                '        :model-value="columns"',
                '        :options="columnOptions"',
                '        @update:model-value="onColumns"',
                '        value-property="id"',
                '        label-property="name"',
                '    />',
            ),
            errors: [{ message: SELECT }],
        },
        {
            name: 'converts a static value to a number',
            filename: 'test.vue',
            code: template('    <sw-select-number-field value="2" :options="[{ id: 1, name: \'One\' }, { id: 2, name: \'Two\' }]" label-property="name"></sw-select-number-field>'),
            output: template('    <mt-select :model-value="2" :options="[{ id: 1, name: \'One\' }, { id: 2, name: \'Two\' }]" label-property="name" value-property="id" />'),
            errors: [{ message: SELECT }],
        },
        manualSelectCase(
            'does not convert option names as locale objects',
            'option names as locale objects',
            "    <sw-select-number-field :options=\"[{ id: 1, name: { 'en-GB': 'One' } }]\" />",
        ),
        manualSelectCase(
            'does not convert aside',
            'aside, which mt-select has no replacement for',
            '    <sw-select-number-field aside :options="columnOptions" />',
        ),
        manualSelectCase(
            'does not convert options that are not numbers',
            'an option value that is not a number',
            '    <sw-select-number-field>',
            '        <option value="small">Small</option>',
            '    </sw-select-number-field>',
        ),
        manualSelectCase(
            'does not convert the label slot',
            'the #label slot',
            '    <sw-select-number-field :options="columnOptions">',
            '        <template #label>Columns</template>',
            '    </sw-select-number-field>',
        ),
        manualSelectCase(
            'does not convert option lists',
            'the v-for attribute on option',
            '    <sw-select-number-field>',
            '        <option v-for="count in counts" :key="count" :value="count">{{ count }}</option>',
            '    </sw-select-number-field>',
        ),
        manualSelectCase(
            'does not convert bound option values',
            'an option value that is not a number',
            '    <sw-select-number-field>',
            '        <option :value="count">{{ count }}</option>',
            '    </sw-select-number-field>',
        ),
        manualSelectCase(
            'does not convert a field without options',
            'missing options',
            '    <sw-select-number-field v-model:value="columns" />',
        ),
        {
            name: 'reports a marked sw-select-number-field without a fix',
            filename: 'test.html.twig',
            code: template(
                `    ${twigTodo(selectManual('missing options'))}`,
                '    <sw-select-number-field v-model:value="columns" />',
            ),
            output: null,
            errors: [{ message: selectManual('missing options') }],
        },
        {
            name: 'converts sw-entity-many-to-many-select in local mode',
            filename: 'test.html.twig',
            code: template(
                '    <sw-entity-many-to-many-select :entity-collection="product.tags" local-mode @update:entity-collection="setTags">',
                '        <template #selection-label-property="{ item }">{{ item.name }}</template>',
                '    </sw-entity-many-to-many-select>',
                '    <sw-entity-many-to-many-select :entity-collection="product.tags" :local-mode="true" />',
            ),
            output: template(
                '    <sw-entity-multi-select :entity-collection="product.tags" @update:entity-collection="setTags">',
                '        <template #selection-label-property="{ item }">{{ item.name }}</template>',
                '    </sw-entity-multi-select>',
                '    <sw-entity-multi-select :entity-collection="product.tags" />',
            ),
            errors: [{ message: MANY_TO_MANY }, { message: MANY_TO_MANY }],
        },
        {
            name: 'marks sw-entity-many-to-many-select that saves itself',
            filename: 'test.html.twig',
            code: template(
                '    <sw-entity-many-to-many-select :entity-collection="product.tags" />',
                '    <sw-entity-many-to-many-select :entity-collection="product.tags" :local-mode="isLocal" />',
            ),
            output: template(
                `    ${twigTodo(MANY_TO_MANY_MANUAL)}`,
                '    <sw-entity-many-to-many-select :entity-collection="product.tags" />',
                `    ${twigTodo(MANY_TO_MANY_MANUAL)}`,
                '    <sw-entity-many-to-many-select :entity-collection="product.tags" :local-mode="isLocal" />',
            ),
            errors: [{ message: MANY_TO_MANY_MANUAL }, { message: MANY_TO_MANY_MANUAL }],
        },
        {
            name: 'marks sw-help-sidebar in .vue files',
            filename: 'test.vue',
            code: template('    <sw-help-sidebar selector="body" />'),
            output: template(
                `    <!-- TODO Codemod(6.8, removed-form-components): ${HELP_SIDEBAR} -->`,
                '    <sw-help-sidebar selector="body" />',
            ),
            errors: [{ message: HELP_SIDEBAR }],
        },
        {
            name: 'reports a marked sw-help-sidebar without a fix',
            filename: 'test.html.twig',
            code: template(`    ${twigTodo(HELP_SIDEBAR)}`, '    <sw-help-sidebar />'),
            output: null,
            errors: [{ message: HELP_SIDEBAR }],
        },
    ],
});
