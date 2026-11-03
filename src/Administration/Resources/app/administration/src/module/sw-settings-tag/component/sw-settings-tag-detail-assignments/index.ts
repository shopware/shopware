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
    name: 'sw-settings-tag-detail-assignments',
    load: () => import('./src/sw-settings-tag-detail-assignments'),
    public: {
        props: [
            'tag',
            'toBeAdded',
            'toBeDeleted',
            'initialCounts',
            'property',
            'entity',
        ],
        emits: [
            'remove-assignment',
            'add-assignment',
        ],
        blocks: [
            'sw_settings_tag_detail_assignments',
            'sw_settings_tag_detail_assignments_card',
            'sw_settings_tag_detail_assignments_toolbar',
            'sw_settings_tag_detail_assignments_header',
            'sw_settings_tag_detail_assignments_header_selected_filter',
            'sw_settings_tag_detail_assignments_header_total_selected',
            'sw_settings_tag_detail_assignments_grid',
            'sw_settings_tag_detail_assignments_associations_grid',
            'sw_settings_tag_detail_assignments_associations_grid_column_name',
            'sw_settings_tag_detail_assignments_entities_grid',
            'sw_settings_tag_detail_assignments_entities_grid_selection_content',
            'sw_settings_tag_detail_assignments_entities_grid_select_item_checkbox',
            'sw_settings_tag_detail_assignments_entities_grid_column_inheritance_switch',
            'sw_settings_tag_detail_assignments_entities_grid_column_name_product',
            'sw_settings_tag_detail_assignments_entities_grid_column_name_category',
            'sw_settings_tag_detail_assignments_entities_grid_column_name_customer',
            'sw_settings_tag_detail_assignments_entities_grid_column_name_order',
            'sw_settings_tag_detail_assignments_entities_grid_column_name_media',
            'sw_settings_tag_detail_assignments_entities_grid_column_name_newsletter_recipient',
            'sw_settings_tag_detail_assignments_entities_grid_column_name_default',
        ],
        computed: [
            'tagDefinition',
            'isInheritable',
            'assignmentAssociations',
            'assignmentAssociationsColumns',
            'entityRepository',
            'entityCriteria',
            'entitiesColumns',
            'selectedAssignments',
            'totalAssignments',
        ],
        methods: [
            'getList',
            'search',
            'addTagAggregations',
            'searchInheritedEntities',
            'onTermChange',
            'onAssignmentChange',
            'onSelectionChange',
            'getCount',
            'countIncrease',
            'countDecrease',
            'isInherited',
            'parentHasTags',
            'hasInheritedTag',
            'onPageChange',
        ],
    },
});
