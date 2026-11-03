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
    name: 'sw-data-grid-column-position',
    load: () => import('./src/sw-data-grid-column-position'),
    public: {
        props: [
            'value',
            'item',
            'field',
            'showValue',
            'disabled',
        ],
        emits: [
            'lower-position-value',
            'position-changed',
            'raise-position-value',
        ],
        blocks: [
            'sw_data_grid_column_position',
            'sw_data_grid_column_position_value',
            'sw_data_grid_column_position_button_group',
            'sw_data_grid_column_position_button_up',
            'sw_data_grid_column_position_button_up_content',
            'sw_data_grid_column_position_button_down',
            'sw_data_grid_column_position_button_down_content',
        ],
        computed: [
            'itemMin',
            'itemMax',
        ],
        methods: [
            'onLowerPositionValue',
            'onRaisePositionValue',
        ],
    },
});
