/**
 * @sw-package framework
 */

const { runCodemod, todo } = require('./tester');

const EDITOR_HINT = 'Use mt-text-editor: v-model:value → v-model, @update:value → @update:model-value, '
    + 'button-config → custom-buttons, allow-inline-data-mapping → createTextEditorDataMappingButton() from '
    + 'Shopware.Component.getComponentHelper() in custom-buttons. sanitize-*, vertical-align and '
    + 'enable-transparent-background have no replacement.';
const TOOLBAR_HINT = 'Configure the toolbar of mt-text-editor with custom-buttons, excluded-buttons and the #button_<name> slots.';
const LINK_MENU_HINT = 'mt-text-editor edits links with sw-text-editor-toolbar-button-link in its #button_link slot.';

const deprecated = (component) => `"${component}" is deprecated. Please use "mt-text-editor" instead.`;
const EDITOR = `${deprecated('sw-text-editor')}\n${EDITOR_HINT}`;
const TOOLBAR = `${deprecated('sw-text-editor-toolbar')}\n${TOOLBAR_HINT}`;
const EDITOR_TODO = todo(`${deprecated('sw-text-editor')} ${EDITOR_HINT}`);
const TOOLBAR_TODO = todo(`${deprecated('sw-text-editor-toolbar')} ${TOOLBAR_HINT}`);

const EDITOR_OVERRIDE = `sw-text-editor is removed. ${EDITOR_HINT}`;
const LINK_MENU_OVERRIDE = `sw-text-editor-link-menu is removed with sw-text-editor. ${LINK_MENU_HINT}`;
const scriptTodo = (message) => `/* TODO Codemod: ${message} */`;

runCodemod('sw-text-editor', {
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
                scriptTodo(EDITOR_OVERRIDE),
                "Component.override('sw-text-editor', {});",
                scriptTodo(LINK_MENU_OVERRIDE),
                "Shopware.Component.extend('acme-link-menu', 'sw-text-editor-link-menu', {});",
            ].join('\n'),
            errors: [{ message: EDITOR_OVERRIDE }, { message: LINK_MENU_OVERRIDE }],
        },
    ],
});

runCodemod('sw-text-editor', {
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
                `    ${EDITOR_TODO}`,
                '    <sw-text-editor v-model:value="item.text" sanitize-input />',
                `    ${TOOLBAR_TODO}`,
                '    <sw-text-editor-toolbar :editor="editor" />',
                '</template>',
            ].join('\n'),
            errors: [{ message: EDITOR }, { message: TOOLBAR }],
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
                `    ${EDITOR_TODO}`,
                '    <SwTextEditor v-model:value="text" />',
                '</template>',
                '<script>',
                scriptTodo(EDITOR_OVERRIDE),
                "Component.extend('acme-editor', 'sw-text-editor', {});",
                '</script>',
            ].join('\n'),
            errors: [{ message: EDITOR }, { message: EDITOR_OVERRIDE }],
        },
    ],
});
