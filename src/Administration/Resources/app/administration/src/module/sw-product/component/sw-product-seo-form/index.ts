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
    name: 'sw-product-seo-form',
    load: () => import('./src/sw-product-seo-form'),
    public: {
        props: [
            'allowEdit',
        ],
        blocks: [
            'sw_product_seo_form',
            'sw_product_seo_form_meta_title',
            'sw_product_seo_form_meta_title_hint',
            'sw_product_seo_form_meta_description',
            'sw_product_seo_form_meta_description_hint',
            'sw_product_seo_form_keywords',
            'sw_product_seo_form_canonical_url',
            'sw_product_seo_form_canonical_url_switch',
            'sw_product_seo_form_canonical_url_select',
            'sw_product_seo_form_canonical_url_select_selection',
            'sw_product_seo_form_canonical_url_select_result',
            'sw_product_seo_form_open_graph',
            'sw_product_seo_form_og_title',
            'sw_product_seo_form_og_description',
            'sw_product_seo_form_og_image',
        ],
        computed: [
            'productRepository',
            'currentOpenGraphMediaId',
            'hasParent',
            'hasVariants',
            'variantCriteria',
            'isCanonicalUrlSelectLoading',
            'variantsWithResetOption',
            'product',
            'parentProduct',
            'isLoading',
            'recommendedMetaTitleLength',
            'recommendedMetaDescriptionLength',
            'mediaRepository',
        ],
        methods: [
            'getRecommendedLengthClass',
            'getRecommendedLengthHint',
            'fetchVariants',
            'getItemName',
            'onSearch',
            'getLoadedOpenGraphMedia',
            'onOpenOgMediaModal',
            'onCloseOgMediaModal',
            'onRemoveOgMedia',
            'onOgMediaUploadFinish',
            'onOgMediaSelectionChange',
        ],
    },
});
