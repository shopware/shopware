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
    name: 'sw-settings-listing-option-criteria-grid',
    load: () => import('./src/sw-settings-listing-option-criteria-grid'),
    public: {
        props: [
            'productSortingEntity',
        ],
        emits: [
            'criteria-add',
            'criteria-delete',
            'inline-edit-save',
            'inline-edit-cancel',
        ],
        blocks: [
            'sw_settings_listing_option_criteria_card',
            'sw_settings_listing_option_criteria_card_toolbar',
            'sw_settings_listing_option_criteria_card_toolbar_select',
            'sw_settings_listing_option_criteria_card_grid',
            'sw_settings_listing_option_criteria_card_grid_column_field',
            'sw_settings_listing_option_criteria_card_grid_column_field_select',
            'sw_settings_listing_option_criteria_card_grid_column_field_custom_field',
            'sw_settings_listing_option_criteria_card_grid_column_field_custom_criteria',
            'sw_settings_listing_option_criteria_card_grid_column_order',
            'sw_settings_listing_option_criteria_card_grid_column_order_text',
            'sw_settings_listing_option_criteria_card_grid_column_order_select',
            'sw_settings_listing_option_criteria_card_grid_column_actions',
            'sw_settings_listing_option_criteria_card_grid_column_actions_delete',
            'sw_settings_listing_option_criteria_card_empty_state',
            'sw_settings_listing_option_criteria_card_empty_state_icon',
        ],
        computed: [
            'customFieldRepository',
            'customFieldSetRepository',
            'customFieldSetRelationsRepository',
            'customFieldCriteria',
            'customFieldsRelationsCriteria',
            'sortedProductSortingFields',
            'productSortingEntityColumns',
            'criteriaOptions',
            'orderOptions',
            'assetFilter',
        ],
        methods: [
            'createdComponent',
            'fetchCustomFieldSetIds',
            'fetchCustomFields',
            'isItemACustomField',
            'getCustomFieldByName',
            'onAddCriteria',
            'getOrderSnippet',
            'onRemoveCriteria',
            'getCriteriaTemplate',
            'onSaveInlineEdit',
            'onCancelInlineEdit',
            'filterEmptyCustomFields',
            'stripCustomFieldPath',
            'getCriteriaSnippetByFieldName',
            'criteriaIsAlreadyUsed',
            'getCustomFieldLabelByCriteriaName',
            'getCustomFieldName',
            'customFieldCriteriaSingleSelect',
            'changeCustomField',
            'getProductSortingFieldsByName',
        ],
    },
});
