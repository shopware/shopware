/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-media-grid-outside-click');
const data = require('./no-media-grid-outside-click.data');

const EVENT = data.events[0].message;
const member = (name) => data.members.find((entry) => entry.name === name).message;
const scriptTodo = (text) => `// TODO Codemod(6.8, media-grid-deselect): ${text}`;
const twigTodo = (text) => `{# TODO Codemod(6.8, media-grid-deselect): ${text} #}`;

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-media-grid-outside-click (script)', rule, {
    valid: [
        { name: 'ignores createdComponent of other components', code: "Component.override('sw-media-library', { methods: { createdComponent() {} } });" },
        { name: 'ignores the sidebar event', code: "this.$emit('media-item-selection-clear');" },
    ],
    invalid: [
        {
            name: 'marks the members inside overrides of sw-media-grid',
            code: [
                "Component.override('sw-media-grid', {",
                '    computed: {',
                '        nonDeselectingComponents() {',
                "            return [...this.$super('nonDeselectingComponents'), 'acme-panel'];",
                '        },',
                '    },',
                '});',
            ].join('\n'),
            output: [
                "Component.override('sw-media-grid', {",
                '    computed: {',
                `        ${scriptTodo(member('nonDeselectingComponents'))}`,
                '        nonDeselectingComponents() {',
                "            return [...this.$super('nonDeselectingComponents'), 'acme-panel'];",
                '        },',
                '    },',
                '});',
            ].join('\n'),
            errors: [{ message: member('nonDeselectingComponents') }],
        },
        {
            name: 'marks emits of the event',
            code: "Component.override('sw-media-grid', { methods: { a() { this.$emit('media-grid-selection-clear'); } } });",
            output: [scriptTodo(EVENT), "Component.override('sw-media-grid', { methods: { a() { this.$emit('media-grid-selection-clear'); } } });"].join('\n'),
            errors: [{ message: EVENT }],
        },
    ],
});

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('no-media-grid-outside-click (template)', rule, {
    valid: [
        {
            name: 'ignores the sidebar listener',
            filename: '/plugin/src/acme-media/acme-media.html.twig',
            code: '<template>\n    <sw-media-sidebar @media-item-selection-clear="clearSelection" />\n</template>',
        },
    ],
    invalid: [
        {
            name: 'marks the listener',
            filename: '/plugin/src/acme-media/acme-media.html.twig',
            code: '<template>\n    <sw-media-grid @media-grid-selection-clear="onClearSelection" />\n</template>',
            output: ['<template>', `    ${twigTodo(EVENT)}`, '    <sw-media-grid @media-grid-selection-clear="onClearSelection" />', '</template>'].join('\n'),
            errors: [{ message: EVENT }],
        },
    ],
});
