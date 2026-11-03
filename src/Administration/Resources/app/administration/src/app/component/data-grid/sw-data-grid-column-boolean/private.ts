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
    name: 'sw-data-grid-column-boolean',
    load: () => import('./src/sw-data-grid-column-boolean'),
    public: {
        props: [
            'isInlineEdit',
            'disabled',
            'value',
        ],
        emits: [
            'update:value',
        ],
        blocks: [
            'sw_data_grid_column_boolean',
        ],
        computed: [
            'currentValue',
        ],
    },
});
