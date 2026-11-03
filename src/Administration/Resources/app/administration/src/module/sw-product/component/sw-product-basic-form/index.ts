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
    name: 'sw-product-basic-form',
    load: () => import('./src/sw-product-basic-form'),
    public: {
        props: [
            'allowEdit',
            'showSettingsInformation',
        ],
        blocks: [
            'sw_product_basic_form',
            'sw_product_basic_form_title_field',
            'sw_product_basic_form_manufacturer_field',
            'sw_product_basic_form_id_field',
            'sw_product_basic_form_cover_image_description_hint',
            'sw_product_basic_form_description_field',
            'sw_product_settings_form_mark_as_topseller_field',
        ],
        computed: [
            'product',
            'parentProduct',
            'isLoading',
            'isTitleRequired',
            'productNumberRangeLink',
            'productNumberHelpText',
            'highlightHelpText',
        ],
        methods: [
            'createdComponent',
            'loadCoverImageDescriptionHintConfig',
            'onCloseCoverImageDescriptionHint',
            'updateIsTitleRequired',
            'getInheritValue',
            'loadProductNumberRangeId',
        ],
    },
});
