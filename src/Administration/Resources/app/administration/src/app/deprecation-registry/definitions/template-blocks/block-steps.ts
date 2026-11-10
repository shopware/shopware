/**
 * @sw-package framework
 *
 * Builds the template block steps of the 6.8 definitions from compact tables.
 */

import { removeTemplateBlock, renameTemplateBlock } from '../helpers';
import type { RemoveTemplateBlockStep, RenameTemplateBlockStep, StepConfig } from '../helpers';

/**
 * Deprecated block names that other Administration templates define as well, for example the
 * `sw_entity_single_select_*` blocks that several CMS element configs override. Their steps only
 * apply to templates of the component they are listed for, so overrides of the other templates stay untouched.
 */
const SHARED_BLOCK_NAMES = new Set([
    'sw_cms_element_image_gallery_config_settings_display_mode',
    'sw_cms_element_image_gallery_config_settings_display_mode_select',
    'sw_cms_element_image_gallery_config_settings_min_height',
    'sw_cms_element_image_gallery_config_settings_vertical_align',
    'sw_custom_field_set_renderer_card_form_renderer',
    'sw_entity_many_to_many_assignment_card_select',
    'sw_entity_multi_select_base_results_list_result_label',
    'sw_entity_single_select_base_results_list_result_label',
    'sw_entity_single_select_variant_result_item',
    'sw_entity_single_select_variant_selected_item',
    'sw_flow_tabs_header_extension',
    'sw_mail_template_detail_preview_modal_footer',
    'sw_mail_template_detail_preview_modal_footer_cancel',
    'sw_product_settings_mode',
    'sw_sales_channel_detail_content_tab_theme',
    'sw_settings_logging_entry_info_content',
    'sw_settings_logging_entry_info_tab_items',
    'sw_settings_storefront',
    'sw_settings_storefront_settings_icon_cache',
    'sw_settings_storefront_settings_speculation_rules',
    'sw_settings_storefront_settings_theme_async',
]);

/** Components whose template other components inherit with `Component.extend()`, blocks included. */
const EXTENDED_BY: Record<string, string[]> = {
    'sw-cms-detail': ['sw-cms-create'],
    'sw-cms-el-config-text': [
        'sw-cms-el-config-product-name',
        'sw-cms-el-config-category-name',
    ],
    'sw-custom-field-set-renderer': ['sw-bulk-edit-custom-fields'],
    'sw-mail-template-detail': ['sw-mail-template-create'],
    'sw-property-search': ['sw-product-variants-configurator-selection'],
    'sw-sales-channel-detail': ['sw-sales-channel-create'],
    'sw-settings-country-detail': ['sw-settings-country-create'],
    'sw-settings-customer-group-detail': ['sw-settings-customer-group-create'],
    'sw-settings-listing-option-base': ['sw-settings-listing-option-create'],
    'sw-settings-logging-entry-info': ['sw-settings-logging-mail-sent-info'],
};

type BlockScope = Pick<RenameTemplateBlockStep, 'component' | 'extendedBy'>;

/**
 * The scope of a step for a block of `component`. A name only that component defines gets none, so the
 * step also matches overrides in files that cannot be assigned to a component. A name other templates
 * share is limited to the component and the components that extend it.
 *
 * @example
 * blockScope('sw-cms-el-config-buy-box', 'sw_cms_element_buy_box_config_options'); // {}
 * blockScope('sw-cms-el-config-buy-box', 'sw_entity_single_select_base_results_list_result_label');
 * // { component: 'sw-cms-el-config-buy-box' }
 */
function blockScope(component: string, from: string): BlockScope {
    if (!SHARED_BLOCK_NAMES.has(from)) {
        return {};
    }

    const extendedBy = EXTENDED_BY[component];

    return extendedBy ? { component, extendedBy } : { component };
}

/**
 * One rename step per entry of `{ component: { oldBlock: newBlock } }`; `config` adds fields per old block.
 *
 * @example
 * renameBlocks({ 'sw-x': { sw_x_tab_content: 'sw_x_mt_tab_content' } });
 */
export function renameBlocks(
    renamesByComponent: Record<string, Record<string, string>>,
    config: (from: string) => Partial<StepConfig<RenameTemplateBlockStep>> = () => ({}),
): RenameTemplateBlockStep[] {
    return Object.entries(renamesByComponent).flatMap(([component, renames]) => {
        return Object.entries(renames).map(([from, to]) => {
            return renameTemplateBlock({ from, to, ...blockScope(component, from), ...config(from) });
        });
    });
}

/**
 * One removal step per removed block of `component`, all moving to the same block (or to none) with
 * the same hint.
 *
 * @example
 * removeBlocks('sw-x', ['sw_x_title', 'sw_x_icon'], { to: 'sw_x_header' });
 */
export function removeBlocks(
    component: string,
    blocks: string[],
    config: Partial<Pick<RemoveTemplateBlockStep, 'to' | 'message'>> = {},
): RemoveTemplateBlockStep[] {
    return blocks.map((from) => removeTemplateBlock({ from, ...blockScope(component, from), ...config }));
}

/**
 * One removal step per entry of `{ component: { newBlock: [removedBlocks] } }`, all with the same hint.
 *
 * @example
 * moveBlocks({ 'sw-x': { sw_x_empty_state: ['sw_x_empty_state_icon'] } }, 'Pass the icon as a prop.');
 */
export function moveBlocks(
    removedBlocksByComponent: Record<string, Record<string, string[]>>,
    message?: string,
): RemoveTemplateBlockStep[] {
    return Object.entries(removedBlocksByComponent).flatMap(([component, removedBlocksByTarget]) => {
        return Object.entries(removedBlocksByTarget).flatMap(([to, blocks]) => {
            return removeBlocks(component, blocks, message ? { to, message } : { to });
        });
    });
}
