import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package inventory
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module and what it inherits from sw-settings-listing-option-base,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-settings-listing-option-create',
    extends: 'sw-settings-listing-option-base',
    load: () => import('./src/sw-settings-listing-option-create'),
    public: {
        blocks: [
            'sw_settings_listing_option_edit_smart_bar_actions_grid',
            'sw_settings_listing_option_base_language_switch',
            'sw_settings_listing_option_base',
            'sw_settings_listing_option_base_smart_bar_heading',
            'sw_settings_listing_option_base_smart_bar_actions',
            'sw_settings_listing_option_base_smart_bar_actions_save',
            'sw_settings_listing_option_base_content',
            'sw_settings_listing_option_base_smart_content',
            'sw_settings_listing_option_base_content_locked_info',
            'sw_settings_listing_option_base_content_general_info',
            'sw_settings_listing_option_base_smart_content_general_info',
            'sw_settings_listing_option_base_content_criteria_grid',
            'sw_settings_listing_option_base_smart_bar_actions_grid',
            'sw_settings_listing_option_base_content_delete_modal',
            'sw_settings_listing_option_base_smart_bar_actions_grid_delete_modal',
        ],
        computed: [
            'smartBarHeading',
            'isNewProductSorting',
            'urlKeyCriteria',
            'productSortingRepository',
            'customFieldRepository',
            'isGeneralCardLoading',
            'customFieldCriteria',
            'productSortingEntityCriteria',
            'isSaveButtonDisabled',
            'isDefaultSorting',
        ],
        methods: [
            'createdComponent',
            'createProductSortingEntity',
            'onSave',
            'onAddCriteria',
            'onConfirmDeleteCriteria',
            'fetchProductSortingEntity',
            'fetchCustomFields',
            'fetchDefaultSorting',
            'getProductSortingEntityId',
            'isValidSortingOption',
            'searchForAlreadyExistingKey',
            'saveProductSorting',
            'getCriteriaTemplate',
            'onDeleteCriteria',
            'onCancelEditCriteria',
            'isCriteriaACustomField',
            'transformCustomFieldCriterias',
            'onChangeLanguage',
        ],
    },
});
