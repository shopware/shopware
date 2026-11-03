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
    name: 'sw-product-detail-variants',
    load: () => import('./src/sw-product-detail-variants'),
    public: {
        blocks: [
            'sw_product_detail_variants',
            'sw_product_detail_variants_sw_card_generated_variants',
            'sw_product_detail_variants_sw_card_empty_state',
            'sw_product_properties_empty_state_button_property',
            'sw_product_detail_variants_sw_card_generated_variants_overview',
            'sw_product_detail_variants_sw_card_empty_state_variant',
            'sw_product_properties_empty_state_button',
            'sw_product_detail_variants_modal_variant_generation',
            'sw_product_detail_variants_modal_delivery',
            'sw_product_properties_add_properties_modal',
        ],
        computed: [
            'product',
            'variants',
            'isStoreLoading',
            'contextLanguageId',
            'productRepository',
            'groupRepository',
            'propertyRepository',
            'variantCardTabs',
            'productProperties',
            'currentProductStates',
            'currentProductType',
            'assetFilter',
            'groupCriteria',
            'configSettingGroups',
        ],
        methods: [
            'createdComponent',
            'mountedComponent',
            'setActiveTab',
            'loadData',
            'loadConfigSettingGroups',
            'loadOptions',
            'loadGroups',
            'updateVariations',
            'updateVariantListHasContent',
            'openModal',
            'onConfigurationClosed',
            'checkIfPropertiesExists',
            'openAddPropertiesModal',
            'closeAddPropertiesModal',
            'updateNewProperties',
            'onCancelAddPropertiesModal',
            'onSaveAddPropertiesModal',
            'loadAllPropertyGroups',
        ],
    },
});
