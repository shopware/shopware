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
    name: 'sw-data-grid-skeleton',
    load: () => import('./src/sw-data-grid-skeleton'),
    public: {
        props: [
            'currentColumns',
            'itemAmount',
            'showSelection',
            'showActions',
            'hasResizeColumns',
        ],
        blocks: [
            'sw_data_grid_skeleton',
            'sw_data_grid_skeleton_row',
            'sw_data_grid_skeleton_cell_selection',
            'sw_data_grid_skeleton_cell_selection_content',
            'sw_data_grid_skeleton_columns',
            'sw_data_grid_skeleton_columns_content',
            'sw_data_grid_skeleton_element',
            'sw_data_grid_skeleton_spacer',
            'sw_data_grid_skeleton_spacer_content',
            'sw_data_grid_skeleton_cell_actions',
            'sw_data_grid_skeleton_cell_actions_content',
        ],
        methods: [
            'getRandomLength',
        ],
    },
});
