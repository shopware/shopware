import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-entity-advanced-selection-modal',
    load: () => import('./src/sw-entity-advanced-selection-modal'),
    public: {
        props: [
            'entityName',
            'entityDisplayText',
            'storeKey',
            'entityColumns',
            'entityFilters',
            'emptyImagePath',
            'emptyIcon',
            'entityAssociations',
            'isSingleSelect',
            'isRecordSelectableCallback',
            'criteriaFilters',
            'criteriaAggregations',
            'entityContext',
            'initialSearchTerm',
            'initialSelection',
            'disablePreviews',
        ],
        emits: [
            'modal-close',
            'selection-submit',
        ],
        slots: [
            '`preview-${column.property}`',
            '`column-${column.property}`',
        ],
        blocks: [
            'sw_entity_advanced_selection_modal',
            'sw_entity_advanced_selection_modal_content_card',
            'sw_entity_advanced_selection_modal_toolbar',
            'sw_entity_advanced_selection_modal_toolbar_filter_button',
            'sw_entity_advanced_selection_modal_toolbar_filter_panel',
            'sw_entity_advanced_selection_modal_toolbar_filter_panel_headline',
            'sw_entity_advanced_selection_modal_toolbar_filter_panel_filters',
            'sw_entity_advanced_selection_modal_toolbar_filter_panel_footer',
            'sw_entity_advanced_selection_modal_list_grid',
            'sw_entity_advanced_selection_modal_list_grid_slots',
            'sw_entity_advanced_selection_modal_list_empty_state',
            'sw_entity_advanced_selection_modal_button_cancel',
            'sw_entity_advanced_selection_modal_button_apply',
        ],
        computed: [
            'modalTitle',
            'entityRepository',
            'entityDefinition',
            'assignmentProperties',
            'allEntityAssociations',
            'entityCriteria',
            'activeFilterNumber',
            'defaultFilters',
            'listFilters',
            'previewColumns',
            'assetFilter',
        ],
        methods: [
            'createdComponent',
            'getList',
            'onSelectionChange',
            'onApply',
            'updateCriteria',
            'debouncedGetList',
            'clearFilters',
        ],
    },
});
