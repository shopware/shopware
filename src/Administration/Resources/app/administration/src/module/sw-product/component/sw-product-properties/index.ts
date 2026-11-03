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
    name: 'sw-product-properties',
    load: () => import('./src/sw-product-properties'),
    public: {
        props: [
            'disabled',
            'isAssociation',
            'showInheritanceSwitcher',
            'emptyStateTitle',
            'emptyStateDescription',
        ],
        blocks: [
            'sw_product_properties',
            'sw_product_properties_card',
            'sw_product_properties_card_title',
            'sw_product_properties_card_title_icon',
            'sw_product_properties_card_title_text',
            'sw_product_properties_filled_state',
            'sw_product_properties_filled_state_header',
            'sw_product_properties_filled_state_header_form_control',
            'sw_product_properties_filled_state_header_button',
            'sw_product_properties_filled_state_body_filled_state',
            'sw_product_properties_filled_state_body_filled_state_column_values',
            'sw_product_properties_filled_state_body_filled_state_delete_modal_delete_item',
            'sw_product_properties_filled_state_body_filled_state_bulk_modal_delete_items',
            'sw_product_properties_filled_state_body_empty_state',
            'sw_product_properties_filled_state_body_empty_state_image',
            'sw_product_properties_filled_state_body_loading_state',
            'sw_product_properties_empty_state',
            'sw_product_properties_empty_state_image',
            'sw_product_properties_empty_state_button',
            'sw_product_properties_add_properties_modal',
        ],
        computed: [
            'propertyGroupRepository',
            'propertyOptionRepository',
            'propertyGroupCriteria',
            'propertyExistsCriteria',
            'propertyColumns',
            'product',
            'parentProduct',
            'isLoading',
            'isChild',
            'productProperties',
            'assetFilter',
            'productHasProperties',
        ],
        methods: [
            'createdComponent',
            'getGroupIds',
            'getProperties',
            'onDeletePropertyValue',
            'onDeleteProperty',
            'onDeleteProperties',
            'onChangeSearchTerm',
            'turnOnAddPropertiesModal',
            'turnOffAddPropertiesModal',
            'updateNewProperties',
            'onCancelAddPropertiesModal',
            'onSaveAddPropertiesModal',
            'checkIfPropertiesExists',
        ],
    },
});
