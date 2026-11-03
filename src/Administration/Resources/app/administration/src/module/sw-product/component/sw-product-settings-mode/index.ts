import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-product-settings-mode',
    load: () => import('./src/sw-product-settings-mode'),
    public: {
        props: [
            'modeSettings',
            'isLoading',
        ],
        emits: [
            'settings-change',
            'settings-item-change',
        ],
        blocks: [
            'sw_product_settings_mode',
            'sw_product_settings_mode_trigger',
            'sw_product_settings_mode_trigger_icon',
            'sw_product_settings_mode_content',
            'sw_product_settings_mode_main',
            'sw_product_settings_mode_advanced_switch',
            'sw_product_settings_mode_devider',
            'sw_product_settings_mode_list',
            'sw_product_settings_mode_item',
            'sw_product_settings_mode_item_visibility_checkbox',
            'sw_product_settings_mode_loader',
        ],
        computed: [
            'advancedMode',
            'settings',
        ],
        methods: [
            'onChangeSetting',
            'onChangeSettingItem',
        ],
    },
});
