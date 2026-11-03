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
    name: 'sw-product-feature-set-form',
    load: () => import('./src/sw-product-feature-set-form'),
    public: {
        props: [
            'allowEdit',
        ],
        blocks: [
            'sw_product_detail_base_feature_set_form',
            'sw_product_feature_set_form_container',
            'sw_product_feature_set_form_description',
            'sw_product_feature_set_form_description_title',
            'sw_product_feature_set_form_description_body',
            'sw_product_feature_set_form_config_information',
            'sw_product_feature_set_form_form',
            'sw_product_feature_set_form_wrapper_input',
            'sw_product_feature_set_form_input',
        ],
        computed: [
            'product',
            'parentProduct',
            'isLoading',
        ],
    },
});
