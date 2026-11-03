import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-privilege-error',
    load: () => import('./src/sw-privilege-error'),
    public: {
        blocks: [
            'sw_privilege_error',
            'sw_privilege_error_inner',
            'sw_privilege_error_inner_content',
            'sw_privilege_error_inner_content_image',
            'sw_privilege_error_inner_content_headline',
            'sw_privilege_error_inner_content_description',
            'sw_privilege_error_inner_content_back_button',
        ],
        computed: [
            'assetFilter',
        ],
        methods: [
            'routerGoBack',
        ],
    },
});
