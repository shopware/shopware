/**
 * @sw-package framework
 *
 * The `sw-text-editor` components that 6.8 removes in favor of `mt-text-editor`, with the TODO text for their tags
 * and for `Component.override/extend` of them. Taken from the `@deprecated tag:v6.8.0` markers.
 */

module.exports = {
    'sw-text-editor': 'sw-text-editor is removed. Use mt-text-editor: v-model:value → v-model, @update:value → @update:model-value, '
        + 'button-config → custom-buttons, allow-inline-data-mapping → createTextEditorDataMappingButton() from '
        + 'Shopware.Component.getComponentHelper() in custom-buttons. sanitize-*, vertical-align and '
        + 'enable-transparent-background have no replacement.',
    'sw-text-editor-toolbar': 'sw-text-editor-toolbar is removed with sw-text-editor. Configure the toolbar of mt-text-editor '
        + 'with custom-buttons, excluded-buttons and the #button_<name> slots.',
    'sw-text-editor-toolbar-button': 'sw-text-editor-toolbar-button is removed with sw-text-editor. Add toolbar buttons to '
        + 'mt-text-editor with custom-buttons or a #button_<name> slot.',
    'sw-text-editor-toolbar-table-button': 'sw-text-editor-toolbar-table-button is removed with sw-text-editor. '
        + 'mt-text-editor has built-in table support.',
    'sw-text-editor-table-toolbar': 'sw-text-editor-table-toolbar is removed with sw-text-editor. '
        + 'mt-text-editor has built-in table support.',
    'sw-text-editor-link-menu': 'sw-text-editor-link-menu is removed with sw-text-editor. '
        + 'mt-text-editor edits links with sw-text-editor-toolbar-button-link in its #button_link slot.',
};
