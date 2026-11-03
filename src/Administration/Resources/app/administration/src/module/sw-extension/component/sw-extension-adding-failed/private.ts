import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-extension-adding-failed',
    load: () => import('./src/sw-extension-adding-failed'),
    public: {
        props: [
            'extensionName',
            'title',
            'detail',
            'documentationLink',
        ],
        emits: [
            'close',
        ],
        blocks: [
            'sw_extension_adding_failed',
            'sw_extension_adding_failed_icon',
            'sw_extension_adding_failed_headline',
            'sw_extension_adding_failed_notification',
            'sw_extension_adding_failed_licence_cancellation',
            'sw_extension_adding_failed_button_close',
        ],
        computed: [
            'myExtensions',
            'extension',
            'hasActiveRentLicense',
            'headline',
            'text',
        ],
    },
});
