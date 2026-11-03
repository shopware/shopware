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
    name: 'sw-settings-product-feature-sets-detail',
    load: () => import('./src/sw-settings-product-feature-sets-detail'),
    public: {
        props: [
            'productFeatureSetId',
        ],
        blocks: [
            'sw_settings_product_feature_set_detail',
            'sw_settings_product_feature_set_detail_header',
            'sw_settings_product_feature_set_detail_language_switch',
            'sw_settings_product_feature_set_detail_actions',
            'sw_settings_product_feature_set_detail_actions_save',
            'sw_settings_product_feature_set_detail_content',
            'sw_settings_product_feature_set_detail_content_language_info',
            'sw_settings_product_feature_set_detail_content_card',
            'sw_settings_product_feature_set_detail_content_field_name',
            'sw_settings_product_feature_set_detail_content_field_description',
            'sw_settings_product_feature_set_detail_content_values_card',
        ],
        computed: [
            'identifier',
            'productFeatureSetsRepository',
            'tooltipSave',
            'tooltipCancel',
        ],
        methods: [
            'createdComponent',
            'loadEntityData',
            'saveFinish',
            'onSave',
            'onCancel',
            'abortOnLanguageChange',
            'saveOnLanguageChange',
            'onChangeLanguage',
        ],
    },
});
