/**
 * @sw-package framework
 *
 * Twig blocks of core Administration templates (and the Administration part of the Storefront) that 6.8 removes.
 * Extracted from the `@deprecated tag:v6.8.0` markers and the legacy branches of `feature.isActive('v6.8.0.0')`,
 * then checked by hand. Blocks that 6.8 renames are in `renamed-in-6-8.ts`.
 *
 * `SHARED` maps names that several core components use to those components.
 * The rule only checks them when it knows which component a template overrides.
 */

import { removeTemplateBlock, templateBlockMigration, upgradeGuide } from '../helpers';
import { removalHint } from './removed-blocks';
import type { RemovedBlocks } from './removed-blocks';
import removedWithLegacyTabs from './removed-with-legacy-tabs';
import removedWithLegacyCmsTabs from './removed-with-legacy-cms-tabs';
import removedByDeprecation from './removed-by-deprecation';

/**
 * @private
 */
export const GROUPS: RemovedBlocks[] = [
    ...removedWithLegacyTabs,
    ...removedWithLegacyCmsTabs,
    ...removedByDeprecation,
];

/**
 * @private
 */
export const SHARED: Record<string, string[]> = {
    sw_cms_element_image_gallery_config_settings_display_mode: [
        'sw-cms-el-config-image-gallery',
        'sw-cms-el-config-image-slider',
    ],
    sw_cms_element_image_gallery_config_settings_display_mode_select: [
        'sw-cms-el-config-image-gallery',
        'sw-cms-el-config-image-slider',
    ],
    sw_cms_element_image_gallery_config_settings_min_height: [
        'sw-cms-el-config-image-gallery',
        'sw-cms-el-config-image-slider',
    ],
    sw_cms_element_image_gallery_config_settings_vertical_align: [
        'sw-cms-el-config-image-gallery',
        'sw-cms-el-config-image-slider',
    ],
    sw_entity_many_to_many_assignment_card_select: ['sw-cms-layout-assignment-modal', 'sw-many-to-many-assignment-card'],
    sw_entity_multi_select_base_results_list_result_label: [
        'sw-category-sales-channel-multi-select',
        'sw-cms-el-config-product-listing',
        'sw-entity-multi-select',
        'sw-product-visibility-select',
    ],
    sw_flow_tabs_header_extension: ['sw-flow-detail', 'sw-flow-index'],
    sw_product_settings_mode: ['sw-product-detail', 'sw-product-settings-mode'],
};

function why(removal: RemovedBlocks): string {
    if (removal.reason === 'tabs') {
        return ' with the legacy sw-tabs branch';
    }

    return removal.reason === 'component' ? ` with the ${removal.component} component` : '';
}

export default templateBlockMigration({
    id: 'template-block.removed-in-6.8',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'Overrides of Twig blocks that 6.8 removes have to move to the blocks or components that replace them.',
    references: [
        upgradeGuide('UPGRADE-6.8.md#removed-deprecated-administration-twig-blocks'),
        upgradeGuide('UPGRADE-6.8.md#removed-administration-twig-blocks-from-legacy-sw-tabs-branches'),
    ],
    usage: GROUPS.flatMap((removal) => {
        return Object.entries(removal.blocks).map(([name, replacement]) => {
            const shared = Object.hasOwn(SHARED, name);

            return removeTemplateBlock({
                from: name,
                components: [removal.component, ...removal.inherited],
                shared,
                message: removalHint({ component: removal.component, shared, why: why(removal), replacement }),
            });
        });
    }),
});
