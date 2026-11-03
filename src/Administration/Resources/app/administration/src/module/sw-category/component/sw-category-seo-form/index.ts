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
    name: 'sw-category-seo-form',
    load: () => import('./src/sw-category-seo-form'),
    public: {
        props: [
            'category',
        ],
        blocks: [
            'sw_category_seo_form',
            'sw_category_seo_form_meta_title',
            'sw_category_seo_form_meta_title_hint',
            'sw_category_seo_form_meta_description',
            'sw_category_seo_form_meta_description_hint',
            'sw_category_seo_form_keywords',
        ],
        computed: [
            'recommendedMetaTitleLength',
            'recommendedMetaDescriptionLength',
            'metaTitleLength',
            'metaDescriptionLength',
        ],
        methods: [
            'getRecommendedLengthClass',
            'getRecommendedLengthHint',
        ],
    },
});
