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
    name: 'sw-settings-listing-option-general-info',
    load: () => import('./src/sw-settings-listing-option-general-info'),
    public: {
        props: [
            'sortingOption',
            'isDefaultSorting',
            'technicalNameError',
            'labelError',
        ],
        blocks: [
            'sw_settings_listing_option_general_info',
            'sw_settings_listing_option_general_info_container',
            'sw_settings_listing_option_general_info_name',
            'sw_settings_listing_option_general_info_technical_name',
            'sw_settings_listing_option_general_info_active',
        ],
    },
});
