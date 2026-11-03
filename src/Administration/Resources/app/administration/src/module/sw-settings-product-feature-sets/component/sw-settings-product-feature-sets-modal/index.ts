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
    name: 'sw-settings-product-feature-sets-modal',
    load: () => import('./src/sw-settings-product-feature-sets-modal'),
    public: {
        props: [
            'productFeatureSet',
        ],
        emits: [
            'modal-close',
        ],
        blocks: [
            'sw_settings_product_feature_sets_modal',
            'sw_settings_product_feature_sets_modal_first_page',
            'sw_settings_product_feature_sets_modal_custom_field_list',
            'sw_settings_product_feature_sets_modal_custom_field_list_toolbar',
            'sw_settings_product_feature_sets_modal_custom_field_list_toolbar_search',
            'sw_settings_product_feature_sets_modal_custom_field_list_column_name',
            'sw_settings_product_feature_sets_modal_custom_field_list_column_type',
            'sw_settings_product_feature_sets_modal_custom_field_list_pagination',
            'sw_settings_product_feature_sets_modal_property_list',
            'sw_settings_product_feature_sets_modal_property_list_toolbar',
            'sw_settings_product_feature_sets_modal_property_list_toolbar_search',
            'sw_settings_product_feature_sets_modal_property_list_column_property',
            'sw_settings_product_feature_sets_modal_property_list_pagination',
            'sw_settings_product_feature_sets_modal_product_information_list',
            'sw_settings_product_feature_sets_modal_product_information_list_toolbar',
            'sw_settings_product_feature_sets_modal_product_informationlist_column_property',
            'sw_settings_product_feature_sets_modal_footer',
            'sw_settings_product_feature_sets_modal_footer_cancel',
            'sw_settings_product_feature_sets_modal_footer_button_add',
            'sw_settings_product_feature_sets_modal_footer_button_next',
        ],
        computed: [
            'productFeatureSetRepository',
            'customFieldsRepository',
            'propertyGroupsRepository',
            'productFeatureSetCriteria',
            'customFieldCriteria',
            'propertyGroupCriteria',
            'referencePriceSelected',
            'propertyGroupColumns',
            'customFieldColumns',
            'productInformationColumns',
            'checkIfReferencePriceSelected',
            'settingOptions',
            'customFieldTotal',
            'propertyGroupTotal',
            'addButtonDisabled',
        ],
        methods: [
            'createdComponent',
            'onSearchCustomFields',
            'onSearchPropertyGroups',
            'onClickNext',
            'getProductInformationList',
            'getCustomFieldList',
            'getPropertyList',
            'getList',
            'getFeaturesIds',
            'onChangeOption',
            'checkIfReferencePriceIsSelected',
            'onConfirm',
            'setFeatures',
            'setFeatureSelection',
            'applySelectionsToActiveGrid',
            'getPropertyGroupColumns',
            'getCustomFieldColumns',
            'getProductInformationColumns',
            'paginateCustomFieldGrid',
            'paginatePropertyGroupGrid',
            'readCustomFieldLabel',
            'getCurrentSelection',
        ],
    },
});
