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
    name: 'sw-data-grid-inline-edit',
    load: () => import('./src/sw-data-grid-inline-edit'),
    public: {
        props: [
            'column',
            'value',
            'compact',
        ],
        emits: [
            'update:value',
        ],
        blocks: [
            'sw_data_grid_inline_edit',
            'sw_data_grid_inline_edit_type_string',
            'sw_data_grid_inline_edit_type_number',
            'sw_data_grid_inline_edit_type_boolean',
            'sw_data_grid_inline_edit_type_unknown',
        ],
        computed: [
            'classes',
            'inputFieldSize',
        ],
        methods: [
            'createdComponent',
            'emitInput',
        ],
    },
});
