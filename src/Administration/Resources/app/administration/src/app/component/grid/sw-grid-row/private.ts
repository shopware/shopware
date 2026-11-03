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
    name: 'sw-grid-row',
    load: () => import('./src/sw-grid-row'),
    public: {
        props: [
            'item',
            'index',
            'allowInlineEdit',
        ],
        emits: [
            'inline-edit-finish',
        ],
        slots: [
            'actions',
            'default',
        ],
        blocks: [
            'sw_grid_row',
            'sw_grid_row_actions',
            'sw_grid_row_slot_actions',
            'sw_grid_row_slot_default',
        ],
        methods: [
            'createdComponent',
            'onInlineEditStart',
            'onInlineEditCancel',
            'onInlineEditFinish',
            'startInlineEditing',
        ],
    },
});
