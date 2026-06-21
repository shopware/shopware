import { reference, renameTemplateBlock, templateBlockMigration } from '../helpers';

export default templateBlockMigration({
    id: 'template-block.renamed-admin-blocks',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description:
        'Several Administration Twig blocks were renamed to fix typos or align names with the new component structure.',
    references: [
        reference({ type: 'upgrade', target: 'UPGRADE-6.7.md#administration' }),
    ],
    usage: [
        renameTemplateBlock({
            from: 'sw_condiiton_date_range_field_to_date',
            to: 'sw_condition_date_range_field_to_date',
        }),
        renameTemplateBlock({
            from: 'sw_cms_detail_stage_empty_stade_content',
            to: 'sw_cms_detail_stage_empty_stage_content',
        }),
        renameTemplateBlock({
            from: 'sw_settings_listing_option_base_smart_content',
            to: 'sw_settings_listing_option_base_content',
        }),
        renameTemplateBlock({
            from: 'sw_settings_listing_option_base_smart_content_general_info',
            to: 'sw_settings_listing_option_base_content_general_info',
        }),
        renameTemplateBlock({
            from: 'sw_settings_listing_option_base_smart_bar_actions_grid',
            to: 'sw_settings_listing_option_base_content_criteria_grid',
        }),
        renameTemplateBlock({
            from: 'sw_settings_listing_option_base_smart_bar_actions_grid_delete_modal',
            to: 'sw_settings_listing_option_base_content_delete_modal',
        }),
    ],
});
