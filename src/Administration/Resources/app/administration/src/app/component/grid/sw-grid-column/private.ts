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
    name: 'sw-grid-column',
    load: () => import('./src/sw-grid-column'),
    public: {
        props: [
            'label',
            'iconLabel',
            'align',
            'flex',
            'sortable',
            'dataIndex',
            'editable',
            'truncate',
        ],
        slots: [
            'default',
            'inline-edit',
        ],
        blocks: [
            'sw_grid_column',
            'sw_grid_column_content',
            'sw_grid_column_slot_default',
            'sw_grid_column_editing',
            'sw_grid_column_slot_inline_edit',
        ],
        computed: [
            'parentGrid',
        ],
        methods: [
            'createdComponent',
            'registerColumn',
        ],
    },
});
