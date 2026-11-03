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
    name: 'sw-settings-listing-delete-modal',
    load: () => import('./src/sw-settings-listing-delete-modal'),
    public: {
        props: [
            'title',
            'description',
        ],
        emits: [
            'cancel',
            'delete',
        ],
        blocks: [
            'sw_settings_listing_delete_modal',
            'sw_settings_listing_delete_modal_body_description',
            'sw_settings_listing_delete_modal_footer',
            'sw_settings_listing_delete_modal_footer_cancel_button',
            'sw_settings_listing_delete_modal_footer_delete_button',
        ],
        methods: [
            'emitCancel',
            'emitDelete',
        ],
    },
});
