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
    name: 'sw-text-editor-table-toolbar',
    load: () => import('./src/sw-text-editor-table-toolbar'),
    public: {
        props: [
            'selection',
        ],
        emits: [
            'table-modify',
            'table-delete',
        ],
        blocks: [
            'sw_text_editor_table_toolbar',
            'sw_text_editor_table_toolbar_buttons',
            'sw_text_editor_table_toolbar_button_row_before',
            'sw_text_editor_table_toolbar_button_row_after',
            'sw_text_editor_table_toolbar_button_row_delete',
            'sw_text_editor_table_toolbar_button_column_before',
            'sw_text_editor_table_toolbar_button_column_after',
            'sw_text_editor_table_toolbar_button_column_delete',
            'sw_text_editor_table_toolbar_button_table_delete',
        ],
        methods: [
            'onAddRow',
            'fillRowWithCells',
            'removeResizeHandle',
            'onAddColumn',
            'insertNewCellsForColumn',
            'onDeleteColumn',
            'setRangeAfterDelete',
            'onDeleteRow',
            'addResizeHandle',
            'deleteCells',
            'onDeleteTable',
            'getVariables',
            'getNode',
            'setSelectionRange',
            'keepSelection',
        ],
    },
});
