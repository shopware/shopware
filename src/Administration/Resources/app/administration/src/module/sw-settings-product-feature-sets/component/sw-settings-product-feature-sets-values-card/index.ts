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
    name: 'sw-settings-product-feature-sets-values-card',
    load: () => import('./src/sw-settings-product-feature-sets-values-card'),
    public: {
        props: [
            'productFeatureSet',
            'isLoading',
            'disabled',
            'allowEdit',
        ],
        blocks: [
            'sw_product_feature_set_card',
            'sw_product_feature_set_toolbar',
            'sw_product_feature_set_toolbar_container',
            'sw_product_feature_set_toolbar_search',
            'sw_product_feature_set_toolbar_delete',
            'sw_product_feature_set_toolbar_add',
            'sw_product_feature_set_card_grid',
            'sw_settings_product_feature_set_card_grid_column_name',
            'sw_settings_product_feature_set_card_grid_column_type',
            'sw_settings_product_feature_set_card_grid_column_position',
            'sw_product_feature_set_card_empty_state',
            'sw_product_feature_set_card_empty_state_button',
            'sw_product_feature_set_card_empty_state_image',
            'sw_product_feature_set_card_empty_state_label',
            'sw_product_feature_set_card_grid_column_modal',
        ],
        computed: [
            'productFeatureSetRepository',
            'propertyGroupRepository',
            'customFieldRepository',
            'valuesEmpty',
            'valuesCardClasses',
            'productFeatureSetCriteria',
            'featureGridTranslationService',
            'assetFilter',
        ],
        methods: [
            'createdComponent',
            'onAddField',
            'onGridSelectionChanged',
            'onSearch',
            'doSearch',
            'getList',
            'onModalClose',
            'onShowFeatureModal',
            'onDeleteFields',
            'onPositionChange',
            'resetPositions',
            'getColumns',
        ],
    },
});
