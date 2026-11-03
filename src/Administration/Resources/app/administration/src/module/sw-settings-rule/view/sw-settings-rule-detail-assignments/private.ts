import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package fundamentals@after-sales
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-settings-rule-detail-assignments',
    load: () => import('./src/sw-settings-rule-detail-assignments'),
    public: {
        props: [
            'rule',
            'conditions',
            'detailPageLoading',
        ],
        blocks: [
            'sw_settings_rule_detail_assignments',
            'sw_settings_rule_detail_assignments_entity_cards',
            'sw_settings_rule_detail_assignments_toolbar',
            'sw_settings_rule_detail_assignments_add_button',
            'sw_settings_rule_detail_assignments_entity_listing',
            'sw_settings_rule_detail_assignments_entity_listing_actions',
            'sw_settings_rule_detail_assignments_entity_listing_view_action',
            'sw_settings_rule_detail_assignments_entity_listing_delete_action',
            'sw_settings_rule_detail_assignments_empty_state',
            'sw_settings_rule_detail_assignments_delete_modal',
            'sw_settings_rule_detail_assignments_delete_modal_text',
            'sw_settings_rule_detail_assignments_delete_modal_footer',
            'sw_settings_rule_detail_assignments_delete_modal_cancel',
            'sw_settings_rule_detail_assignments_delete_modal_confirm',
            'sw_settings_rule_detail_assignments_delete_modal_confirm_single',
            'sw_settings_rule_detail_assignments_add_modal',
        ],
        computed: [
            'getRuleAssignmentConfiguration',
            'associationEntitiesConfig',
            'assetFilter',
        ],
        methods: [
            'createdComponent',
            'disableAdd',
            'getTooltipConfig',
            'allowDeletion',
            'prepareAssociationEntitiesList',
            'onOpenDeleteModal',
            'onCloseDeleteModal',
            'onOpenAddModal',
            'onCloseAddModal',
            'onEntitiesSaved',
            'onDeleteItems',
            'onDelete',
            'doDeleteItem',
            'refreshAssignmentData',
            'onFilterEntity',
            'loadNotAssignedDataTotals',
            'getRouterLink',
            'hasRoute',
            'loadAssociationData',
        ],
    },
});
