/**
 * @sw-package framework
 *
 * Builders for many steps of one kind at once, for deprecations that rename or remove a long
 * list of blocks or members. Each entry becomes the same step its single builder creates.
 */

import type { ComponentMemberStep, FixLevel, RemoveTemplateBlockStep, RenameTemplateBlockStep } from '../types';
import { componentMember, removeTemplateBlock, renameTemplateBlock } from './simple-steps';

const ACTIVE_SLOT_REVIEW = 'The block no longer receives the active slot variable of sw-tabs, use activeTab instead.';

type TemplateBlockList = {
    /** The component whose template defines the blocks, then the components that inherit its template. */
    components: string[];
    /** Names another core template defines as well, so they only match in templates of `components`. */
    sharedNames?: string[];
};

/**
 * Renames overrides of the blocks of one component, `{ old: new }`.
 *
 * @example
 * renameTemplateBlocks({
 *     components: ['sw-cms-detail', 'sw-cms-create'],
 *     blocks: { sw_cms_detail_stage_empty_stade_content: 'sw_cms_detail_stage_empty_stage_content' },
 * });
 */
export function renameTemplateBlocks({
    components,
    sharedNames = [],
    activeSlot = [],
    blocks,
}: TemplateBlockList & {
    blocks: Record<string, string>;
    /** Blocks of a legacy `sw-tabs` content slot, which read its `active` variable; renamed with a review. */
    activeSlot?: string[];
}): RenameTemplateBlockStep[] {
    return Object.entries(blocks).map(([from, to]) => {
        return renameTemplateBlock({
            from,
            to,
            components,
            ...(sharedNames.includes(from) ? { onlyInComponents: true } : {}),
            ...(activeSlot.includes(from) ? { fix: 'unsafe-auto' as FixLevel, message: ACTIVE_SLOT_REVIEW } : {}),
        });
    });
}

/**
 * Reports overrides of the removed blocks of one component, `{ block: where to move it, or null }`.
 *
 * @example
 * removeTemplateBlocks({
 *     components: ['sw-settings-snippet-set-list'],
 *     blocks: { sw_settings_snippet_set_list_card_title: 'sw_settings_snippet_set_list_card_list' },
 * });
 */
export function removeTemplateBlocks({
    components,
    sharedNames = [],
    blocks,
}: TemplateBlockList & { blocks: Record<string, string | null> }): RemoveTemplateBlockStep[] {
    return Object.entries(blocks).map(([from, moveTo]) => {
        return removeTemplateBlock({
            from,
            components,
            ...(moveTo ? { moveTo } : {}),
            ...(sharedNames.includes(from) ? { onlyInComponents: true } : {}),
        });
    });
}

/**
 * Reports, or with `replaceInScript` rewrites, members several components lose, see `componentMember`.
 *
 * @example
 * componentMembers({ components: ['sw-order-detail-details'], members: ['shippingCosts', 'currency'] });
 */
export function componentMembers({
    members,
    ...config
}: Omit<ComponentMemberStep, 'kind' | 'fix' | 'member'> & { members: string[]; fix?: FixLevel }): ComponentMemberStep[] {
    return members.map((member) => componentMember({ ...config, member }));
}
