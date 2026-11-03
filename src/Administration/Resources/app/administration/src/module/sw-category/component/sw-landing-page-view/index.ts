import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-landing-page-view',
    load: () => import('./src/sw-landing-page-view'),
    public: {
        props: [
            'isLoading',
        ],
        blocks: [
            'sw_landing_page_view',
            'sw_landing_page_view_language_info',
            'sw_landing_page_view_tabs',
            'sw_landing_page_view_mt_tabs',
            'sw_landing_page_view_mt_tabs_permission_warning',
            'sw_landing_page_view_tabs_general',
            'sw_landing_page_view_tabs_cms',
            'sw_landing_page_view_content',
        ],
        computed: [
            'landingPage',
            'cmsPage',
            'landingPageViewTabs',
        ],
    },
});
