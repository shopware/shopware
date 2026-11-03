import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-text-editor-toolbar-table-button',
    load: () => import('./src/sw-text-editor-toolbar-table-button'),
    public: {
        props: [
            'buttonConfig',
        ],
        emits: [
            'mounted',
            'table-create',
        ],
        blocks: [
            'sw_text_editor_toolbar_table_button',
            'sw_text_editor_toolbar_table_button_table_head_switch',
            'sw_text_editor_toolbar_table_button_table_grid_input',
            'sw_text_editor_toolbar_table_button_table',
            'sw_text_editor_toolbar_table_button_table_head',
            'sw_text_editor_toolbar_table_button_table_head_row',
            'sw_text_editor_toolbar_table_button_table_head_col',
            'sw_text_editor_toolbar_table_button_table_body',
            'sw_text_editor_toolbar_table_button_table_body_row',
            'sw_text_editor_toolbar_table_button_table_body_col',
            'sw_text_editor_toolbar_table_button_table_insert',
        ],
        methods: [
            'mountedComponent',
            'onMouseOverColumn',
            'setSelectedTableColsAndRows',
            'setSelectedCols',
            'setSelectedRows',
            'loopTableRows',
            'loopTableCols',
            'onMouseOut',
            'onLastRowMouseOut',
            'onLastColMouseOut',
            'emitTable',
            'createHtmlTable',
        ],
    },
});
