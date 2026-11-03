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
    name: 'sw-data-grid-settings',
    load: () => import('./src/sw-data-grid-settings'),
    public: {
        props: [
            'columns',
            'compact',
            'previews',
            'enablePreviews',
            'disabled',
        ],
        emits: [
            'change-compact-mode',
            'change-preview-images',
            'change-column-visibility',
            'change-column-order',
        ],
        slots: [
            'additionalSettings',
        ],
        blocks: [
            'sw_data_grid_settings',
            'sw_data_grid_settings_trigger',
            'sw_data_grid_settings_trigger_icon',
            'sw_data_grid_settings_content',
            'sw_data_grid_settings_general',
            'sw_data_grid_settings_compact_switch',
            'sw_data_grid_settings_preview_switch',
            'sw_data_grid_settings_additional_settings',
            'sw_data_grid_settings_additional_settings_slot',
            'sw_data_grid_settings_devider',
            'sw_data_grid_settings_columns',
            'sw_data_grid_settings_column_element',
            'sw_data_grid_settings_column_visibility_checkbox',
            'sw_data_grid_settings_column_item_conrols',
            'sw_data_grid_settings_column_control_up',
            'sw_data_grid_settings_column_control_up_icon',
            'sw_data_grid_settings_column_control_down',
            'sw_data_grid_settings_column_control_down_icon',
        ],
        computed: [
            'contextMenuClasses',
        ],
        methods: [
            'onChangeCompactMode',
            'onChangePreviews',
            'onChangeColumnVisibility',
            'onClickChangeColumnOrderUp',
            'onClickChangeColumnOrderDown',
            'getColumnLabel',
        ],
    },
});
