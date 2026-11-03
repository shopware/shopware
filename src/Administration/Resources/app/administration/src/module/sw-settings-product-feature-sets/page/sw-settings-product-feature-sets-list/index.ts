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
    name: 'sw-settings-product-feature-sets-list',
    load: () => import('./src/sw-settings-product-feature-sets-list'),
    public: {
        blocks: [
            'sw_settings_list',
            'sw_settings_product_feature_set_index',
            'sw_settings_product_feature_set_list_search_bar',
            'sw_settings_product_feature_set_list_smart_bar_header',
            'sw_settings_product_feature_set_list_smart_bar_header_title',
            'sw_settings_product_feature_set_list_smart_bar_header_title_text',
            'sw_settings_product_feature_set_list_smart_bar_actions',
            'sw_settings_product_feature_set_list_smart_bar_actions_add',
            'sw_settings_product_feature_set_list_smart_bar_language_switch',
            'sw_settings_product_feature_set_list_content',
            'sw_settings_product_feature_set_list_grid_columns_actions',
            'sw_settings_product_feature_set_list_grid_columns_actions_edit',
            'sw_settings_product_feature_set_list_grid_columns_actions_delete',
            'sw_settings_product_feature_set_list_grid_action_modals',
            'sw_settings_product_feature_set_list_delete_modal',
            'sw_settings_product_feature_set_list_delete_modal_confirm_delete_text',
            'sw_settings_product_feature_set_list_delete_modal_footer',
            'sw_settings_product_feature_set_list_delete_modal_cancel',
            'sw_settings_product_feature_set_list_delete_modal_confirm',
        ],
        computed: [
            'productFeatureSetsRepository',
            'propertyGroupRepository',
            'customFieldRepository',
            'featureGridTranslationService',
        ],
        methods: [
            'metaInfo',
            'getList',
            'onChangeLanguage',
            'onInlineEditSave',
            'onDelete',
            'onCloseDeleteModal',
            'onConfirmDelete',
            'getProductFeatureSetsColumns',
            'renderFeaturePreview',
        ],
    },
});
