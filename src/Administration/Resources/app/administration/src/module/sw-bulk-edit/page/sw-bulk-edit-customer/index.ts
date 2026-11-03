import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package checkout
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-bulk-edit-customer',
    load: () => import('./src/sw-bulk-edit-customer'),
    public: {
        blocks: [
            'sw_bulk_edit_customer',
            'sw_bulk_edit_customer_search_bar',
            'sw_bulk_edit_customer_smart_bar_header',
            'sw_bulk_edit_customer_content_language_switch',
            'sw_bulk_edit_customer_content_smart_bar_actions',
            'sw_bulk_edit_customer_actions_save',
            'sw_bulk_edit_customer_content',
            'sw_bulk_edit_customer_content_account_card',
            'sw_bulk_edit_customer_content_account_section',
            'sw_bulk_edit_customer_tags_card',
            'sw_bulk_edit_customer_tags',
            'sw_bulk_edit_customer_custom_field_card',
            'sw_bulk_edit_customer_empty_state',
            'sw_bulk_edit_customer_save_modal',
        ],
        computed: [
            'selectedIds',
            'customFieldSetRepository',
            'customerRepository',
            'customFieldSetCriteria',
            'hasChanges',
            'actionsRequestGroup',
            'accountFormFields',
            'tagsFormFields',
        ],
        methods: [
            'createdComponent',
            'setRouteMetaModule',
            'defineBulkEditData',
            'loadBulkEditData',
            'loadCustomFieldSets',
            'onCustomFieldsChange',
            'onProcessData',
            'openModal',
            'onSave',
            'closeModal',
            'onChangeLanguage',
        ],
    },
});
