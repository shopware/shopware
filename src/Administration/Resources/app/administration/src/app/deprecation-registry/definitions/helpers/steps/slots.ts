/* eslint-disable @typescript-eslint/no-unsafe-member-access -- vue-eslint-parser template nodes are untyped records */
/**
 * @sw-package framework
 */

import type {
    ComponentUsageRuleApi,
    Fixer,
    FlagSlotForManualMoveStep,
    RemoveSlotStep,
    RenameSlotStep,
    SlotTextToPropStep,
    StepConfig,
    TemplateNode,
} from '../types';
import type { UsagePlan } from '../shared';
import { defineUsageStep, reportUsage } from '../shared';

/**
 * Handles a slot the new component no longer has.
 *
 * - empty → removed
 * - with content → manual with `hint`, because the new component does not render it and only a
 *   developer can decide where it goes
 *
 * @example
 * removeSlot({ slot: 'actions', hint: 'mt-banner has no "actions" slot. Render the actions next to the banner.' });
 * // <template #actions></template> → removed
 * // <template #actions><mt-button /></template> → manual
 */
export function removeSlot({ fix = 'auto', ...config }: StepConfig<RemoveSlotStep>): RemoveSlotStep {
    return defineUsageStep<RemoveSlotStep>({ kind: 'remove-slot', fix, ...config }, (api, step) => {
        const slot = api.ast.findSlot(api.node, step.slot);

        if (!slot) {
            return;
        }

        const isEmpty = api.ast.getSlotContentValue(slot)?.kind === 'none';

        reportUsage(api, {
            node: slot,
            apiName: step.slot,
            headline: `The "${step.slot}" slot is removed.`,
            ...(isEmpty
                ? { fix: (fixer: Fixer) => fixer.removeRange(api.ast.getRemovalRange(slot)) }
                : {
                      manualReason:
                          step.hint ??
                          `The "${step.slot}" slot does not exist anymore, so its content is not rendered. Move or remove it.`,
                  }),
        });
    });
}

/**
 * Renames a slot the replacement component renders under another name, keeping its slot props.
 *
 * - `#from` and `v-slot:from` → renamed, every template that fills it (`v-if` / `v-else`)
 * - the element already fills `to` → manual, both slots have to be merged
 *
 * @example
 * renameSlot({ from: 'header-right', to: 'headerRight' });
 * // <template #header-right="{ item }"> → <template #headerRight="{ item }">
 */
export function renameSlot({ fix = 'auto', ...config }: StepConfig<RenameSlotStep>): RenameSlotStep {
    return defineUsageStep<RenameSlotStep>({ kind: 'rename-slot', fix, ...config }, (api, step) => {
        const fillsTarget = findSlotDirectives(api, step.to).length > 0;

        // Several templates can fill the slot, for example with v-if and v-else
        findSlotDirectives(api, step.from).forEach((slotDirective) => {
            reportUsage(api, {
                node: slotDirective,
                apiName: step.from,
                headline: `The "${step.from}" slot is renamed. Use "${step.to}" instead.`,
                ...(fillsTarget
                    ? { manualReason: `The element already fills the "${step.to}" slot. Merge both slots manually.` }
                    : { fix: (fixer) => fixer.replaceText(slotDirective.key.argument as TemplateNode, step.to) }),
            });
        });
    });
}

/**
 * Finds the `v-slot` directives of the child templates that fill the named slot. Slot names are
 * compared case-insensitively only, because the parser lowercases them and `header-right` and
 * `headerRight` are different slots.
 *
 * @example
 * findSlotDirectives(api, 'headerRight'); // the #headerRight attribute of <template #headerRight>
 */
function findSlotDirectives(api: ComponentUsageRuleApi, slotName: string): TemplateNode[] {
    const children = api.node.children as TemplateNode[];

    return children
        .filter((child) => child.type === 'VElement' && child.name === 'template')
        .flatMap((child) => child.startTag.attributes as TemplateNode[])
        .filter((attribute) => {
            const argumentName = api.ast.getDirectiveArgumentName(attribute);

            return api.ast.getDirectiveName(attribute) === 'slot' && argumentName?.toLowerCase() === slotName.toLowerCase();
        });
}

/**
 * Moves the text of a removed slot into a prop. For a slot whose value can also come from other
 * places, use `propFromSource()`.
 *
 * - plain text → static prop
 * - a single mustache → bound prop
 * - text mixed with mustaches → bound template literal
 * - an empty slot → removed
 * - markup, Twig, a conditional or scoped slot, or `toProp` already set → manual
 *
 * @example
 * slotTextToProp({ fromSlot: 'label', toProp: 'label' });
 * // <template #label>Name</template> → label="Name"
 * // <template #label>{{ $t('sw-foo.label') }}</template> → :label="$t('sw-foo.label')"
 * // <template #label>{{ count }} items</template> → :label="`${count} items`"
 */
export function slotTextToProp({ fix = 'auto', ...config }: StepConfig<SlotTextToPropStep>): SlotTextToPropStep {
    return defineUsageStep<SlotTextToPropStep>({ kind: 'slot-text-to-prop', fix, ...config }, (api, step) => {
        const slot = api.ast.findSlot(api.node, step.fromSlot);

        if (!slot) {
            return;
        }

        reportUsage(api, {
            node: slot,
            apiName: step.fromSlot,
            headline: `The "${step.fromSlot}" slot is removed. Pass its content as the "${step.toProp}" prop.`,
            ...planSlotToProp(api, step, slot),
        });
    });
}

function planSlotToProp(api: ComponentUsageRuleApi, step: SlotTextToPropStep, slot: TemplateNode): UsagePlan {
    const manualMove = `Move the content of the "${step.fromSlot}" slot into the "${step.toProp}" prop manually.`;

    if (isConditionalOrScopedSlot(api, slot)) {
        return { manualReason: `The slot is rendered conditionally or uses slot props. ${manualMove}` };
    }

    if (api.ast.hasMatchingPropAttribute(api.node, step.toProp)) {
        return { manualReason: `The element already sets "${step.toProp}". ${manualMove}` };
    }

    const value = api.ast.getSlotContentValue(slot);

    if (value === null) {
        return { manualReason: `The slot contains markup or Twig, which a prop cannot hold. ${manualMove}` };
    }

    const removeSlotElement = (fixer: Fixer) => fixer.removeRange(api.ast.getRemovalRange(slot));

    if (value.kind === 'none') {
        return { fix: removeSlotElement };
    }

    const attribute = api.ast.toPropAttribute(step.toProp, value);

    if (attribute === null) {
        return { manualReason: `The slot content mixes single and double quotes. ${manualMove}` };
    }

    const tagNameEnd = (api.node.startTag.range[0] as number) + 1 + (api.node.rawName as string).length;

    return {
        produces: { kind: 'prop', name: step.toProp },
        fix: (fixer) => [
            fixer.insertTextAfterRange([tagNameEnd, tagNameEnd], ` ${attribute}`),
            removeSlotElement(fixer),
        ],
    };
}

/**
 * Tells whether a slot template renders conditionally (`v-if` and the like) or receives slot props,
 * so its content cannot become a plain prop value.
 *
 * @example
 * isConditionalOrScopedSlot(api, slot); // true for <template #label="{ item }"> and <template v-if="x" #label>
 */
export function isConditionalOrScopedSlot(api: ComponentUsageRuleApi, slot: TemplateNode): boolean {
    return (slot.startTag.attributes as TemplateNode[]).some((attribute) => {
        return api.ast.getDirectiveName(attribute) !== 'slot' || Boolean(attribute.value);
    });
}

/** Builder input of `flagSlotForManualMove`: a custom `hint`, or the prop the slot content moves to. */
export type FlagSlotForManualMoveConfig = {
    slot: string;
    message?: string;
} & ({ hint: string; toProp?: never } | { toProp: string; hint?: never });

/**
 * Reports a slot whose content cannot move automatically for a manual move. Use it when the content
 * is markup that only a developer can turn into a prop or move elsewhere.
 *
 * - filled → always manual, with `hint` or "use the `toProp` prop instead"
 *
 * @example
 * flagSlotForManualMove({ slot: 'label', toProp: 'label' });
 * // reason: Remove the "label" slot and use the "label" prop instead.
 * flagSlotForManualMove({ slot: 'content', hint: 'Set the content outside the component.' });
 */
export function flagSlotForManualMove({
    slot,
    toProp,
    hint,
    message,
}: FlagSlotForManualMoveConfig): FlagSlotForManualMoveStep {
    return defineUsageStep<FlagSlotForManualMoveStep>(
        {
            kind: 'flag-slot-for-manual-move',
            slot,
            hint: hint ?? `Remove the "${slot}" slot and use the "${toProp}" prop instead.`,
            fix: 'manual',
            ...(message === undefined ? {} : { message }),
        },
        (api, step) => {
            const target = findSlotContent(api, step.slot);

            if (!target) {
                return;
            }

            reportUsage(api, {
                node: target,
                apiName: step.slot,
                headline: `The "${step.slot}" slot is removed.`,
                manualReason: step.hint,
            });
        },
    );
}

/**
 * Finds the `<template #slot>` of a slot, or for the default slot also its first element written
 * without a template.
 *
 * @example
 * findSlotContent(api, 'default'); // <option> in <mt-select><option /></mt-select>
 */
export function findSlotContent(api: ComponentUsageRuleApi, slotName: string): TemplateNode | undefined {
    const slot = api.ast.findSlot(api.node, slotName);

    if (slot || slotName !== 'default') {
        return slot;
    }

    return api.ast.getFirstElementChildWithoutSlot(api.node);
}
