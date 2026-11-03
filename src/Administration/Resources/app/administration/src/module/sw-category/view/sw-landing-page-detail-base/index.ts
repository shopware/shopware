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
    name: 'sw-landing-page-detail-base',
    load: () => import('./src/sw-landing-page-detail-base'),
    public: {
        props: [
            'isLoading',
        ],
        blocks: [
            'sw_landing_page_detail_base',
            'sw_landing_page_detail_base_information',
            'sw_landing_page_detail_base_information_name',
            'sw_landing_page_detail_base_information_active',
            'sw_landing_page_detail_base_seo_form_sales_channel',
            'sw_landing_page_detail_base_information_tags',
            'sw_landing_page_detail_base_seo',
            'sw_landing_page_detail_base_seo_form',
            'sw_landing_page_detail_base_seo_form_meta_title',
            'sw_landing_page_detail_base_seo_form_meta_description',
            'sw_landing_page_detail_base_seo_form_keywords',
            'sw_landing_page_detail_base_seo_form_url',
            'sw_landing_page_detail_base_attribute_sets',
        ],
        computed: [
            'customFieldSetsArray',
            'landingPage',
            'cmsPage',
            'isLayoutSet',
        ],
    },
});
