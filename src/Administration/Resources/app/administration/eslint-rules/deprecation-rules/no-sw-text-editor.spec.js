/**
 * @sw-package framework
 */

const { RuleTester } = require('eslint');
const rule = require('./no-sw-text-editor');
const components = require('./no-sw-text-editor.data');

const EDITOR = components['sw-text-editor'];
const TOOLBAR = components['sw-text-editor-toolbar'];
const LINK_MENU = components['sw-text-editor-link-menu'];

const scriptTodo = (message) => `// TODO Codemod(6.8, sw-text-editor): ${message}`;
const twigTodo = (message) => `{# TODO Codemod(6.8, sw-text-editor): ${message} #}`;

describe('no-sw-text-editor data', () => {
    it('covers the six removed components', () => {
        expect(Object.keys(components)).toEqual([
            'sw-text-editor',
            'sw-text-editor-toolbar',
            'sw-text-editor-toolbar-button',
            'sw-text-editor-toolbar-table-button',
            'sw-text-editor-table-toolbar',
            'sw-text-editor-link-menu',
        ]);
    });

    it('names the prop mapping for sw-text-editor', () => {
        expect(EDITOR).toContain('v-model:value → v-model');
        expect(EDITOR).toContain('@update:value → @update:model-value');
        expect(EDITOR).toContain('button-config → custom-buttons');
    });
});

new RuleTester({ languageOptions: { ecmaVersion: 2022, sourceType: 'module' } }).run('no-sw-text-editor (script)', rule, {
    valid: [
        { name: 'ignores mt-text-editor', code: "Component.override('mt-text-editor', {});" },
        { name: 'ignores other components', code: "Component.override('sw-text-field', {});" },
    ],
    invalid: [
        {
            name: 'marks overrides and extensions',
            code: [
                "Component.override('sw-text-editor', {});",
                "Shopware.Component.extend('acme-link-menu', 'sw-text-editor-link-menu', {});",
            ].join('\n'),
            output: [
                scriptTodo(EDITOR),
                "Component.override('sw-text-editor', {});",
                scriptTodo(LINK_MENU),
                "Shopware.Component.extend('acme-link-menu', 'sw-text-editor-link-menu', {});",
            ].join('\n'),
            errors: [{ message: EDITOR }, { message: LINK_MENU }],
        },
        {
            name: 'reports a marked override without a fix',
            code: [scriptTodo(EDITOR), "Component.override('sw-text-editor', {});"].join('\n'),
            output: null,
            errors: [{ message: EDITOR }],
        },
    ],
});

new RuleTester({ languageOptions: { parser: require('vue-eslint-parser'), ecmaVersion: 2022 } }).run('no-sw-text-editor (template)', rule, {
    valid: [
        {
            name: 'ignores mt-text-editor',
            filename: 'test.html.twig',
            code: '<template>\n    <mt-text-editor v-model="item.text" />\n</template>',
        },
    ],
    invalid: [
        {
            name: 'marks the tags in Twig templates',
            filename: 'test.html.twig',
            code: [
                '<template>',
                '    <sw-text-editor v-model:value="item.text" sanitize-input />',
                '    <sw-text-editor-toolbar :editor="editor" />',
                '</template>',
            ].join('\n'),
            output: [
                '<template>',
                `    ${twigTodo(EDITOR)}`,
                '    <sw-text-editor v-model:value="item.text" sanitize-input />',
                `    ${twigTodo(TOOLBAR)}`,
                '    <sw-text-editor-toolbar :editor="editor" />',
                '</template>',
            ].join('\n'),
            errors: [{ message: EDITOR }, { message: TOOLBAR }],
        },
        {
            name: 'reports a marked tag without a fix',
            filename: 'test.html.twig',
            code: ['<template>', `    ${twigTodo(EDITOR)}`, '    <sw-text-editor />', '</template>'].join('\n'),
            output: null,
            errors: [{ message: EDITOR }],
        },
        {
            name: 'marks the tags and the script of .vue files',
            filename: 'test.vue',
            code: [
                '<template>',
                '    <SwTextEditor v-model:value="text" />',
                '</template>',
                '<script>',
                "Component.extend('acme-editor', 'sw-text-editor', {});",
                '</script>',
            ].join('\n'),
            output: [
                '<template>',
                `    <!-- TODO Codemod(6.8, sw-text-editor): ${EDITOR} -->`,
                '    <SwTextEditor v-model:value="text" />',
                '</template>',
                '<script>',
                scriptTodo(EDITOR),
                "Component.extend('acme-editor', 'sw-text-editor', {});",
                '</script>',
            ].join('\n'),
            errors: [{ message: EDITOR }, { message: EDITOR }],
        },
    ],
});
