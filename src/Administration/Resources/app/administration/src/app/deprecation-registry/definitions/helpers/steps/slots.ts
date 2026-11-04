/* eslint-disable @typescript-eslint/no-unsafe-member-access -- vue-eslint-parser template nodes are untyped records */
/**
 * @sw-package framework
 */

import type {
    ComponentUsageRuleApi,
    Fixer,
    FixLevel,
    FlagSlotForManualMoveStep,
    RemoveSlotStep,
    SlotToPropStep,
    StepConfig,
    TemplateNode,
} from '../types';
import type { UsagePlan } from '../shared';
import { defineUsageStep, reportUsage } from '../shared';

/**
 * Replaces a removed slot with a comment that keeps its text content, or with `replacementComment`.
 *
 * @example
 * removeSlot({ slot: 'hint' });
 * removeSlot({ slot: 'actions', replacementComment: 'Slot "actions" was removed and has no replacement.' });
 */
export function removeSlot({ fix = 'auto', ...config }: StepConfig<RemoveSlotStep>): RemoveSlotStep {
    return defineUsageStep<RemoveSlotStep>({ kind: 'remove-slot', fix, ...config }, (api, step) => {
        const slot = api.ast.findSlot(api.node, step.slot);

        if (!slot) {
            return;
        }

        const comment =
            step.replacementComment ??
            `Slot "${step.slot}" was removed without replacement. Previous value was: ${api.ast.getCondensedTextContent(slot)}`;

        reportUsage(api, {
            node: slot,
            apiName: step.slot,
            fix: (fixer) => fixer.replaceText(slot, `<!-- ${comment} -->`),
        });
    });
}

/**
 * Moves the content of a removed slot into a prop: plain text becomes a static prop, a single mustache a
 * bound prop, mixed text and mustaches a template literal. Markup, Twig, a conditional or scoped slot,
 * or a prop that is already set leave the slot for a manual move.
 *
 * @example
 * slotToProp({ slot: 'label', prop: 'label' });
 * // <template #label>{{ $t('sw-foo.label') }}</template> → :label="$t('sw-foo.label')"
 */
export function slotToProp({ fix = 'auto', ...config }: StepConfig<SlotToPropStep>): SlotToPropStep {
    return defineUsageStep<SlotToPropStep>({ kind: 'slot-to-prop', fix, ...config }, (api, step) => {
        const slot = api.ast.findSlot(api.node, step.slot);

        if (!slot) {
            return;
        }

        reportUsage(api, {
            node: slot,
            apiName: step.slot,
            headline: `The "${step.slot}" slot is removed. Pass its content as the "${step.prop}" prop.`,
            ...planSlotToProp(api, step, slot),
        });
    });
}

function planSlotToProp(api: ComponentUsageRuleApi, step: SlotToPropStep, slot: TemplateNode): UsagePlan {
    const manualMove = `Move the content of the "${step.slot}" slot into the "${step.prop}" prop manually.`;

    if (isConditionalOrScopedSlot(api, slot)) {
        return { manualReason: `The slot is rendered conditionally or uses slot props. ${manualMove}` };
    }

    if (api.ast.hasMatchingPropAttribute(api.node, step.prop)) {
        return { manualReason: `The element already sets "${step.prop}". ${manualMove}` };
    }

    const value = api.ast.getSlotContentValue(slot);

    if (value === null) {
        return { manualReason: `The slot contains markup or Twig, which a prop cannot hold. ${manualMove}` };
    }

    const removeSlotElement = (fixer: Fixer) => fixer.removeRange(api.ast.getRemovalRange(slot));

    if (value.kind === 'none') {
        return { fix: removeSlotElement };
    }

    const attribute = api.ast.toPropAttribute(step.prop, value);

    if (attribute === null) {
        return { manualReason: `The slot content mixes single and double quotes. ${manualMove}` };
    }

    const tagNameEnd = (api.node.startTag.range[0] as number) + 1 + (api.node.rawName as string).length;

    return {
        produces: { kind: 'prop', name: step.prop },
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
function isConditionalOrScopedSlot(api: ComponentUsageRuleApi, slot: TemplateNode): boolean {
    return (slot.startTag.attributes as TemplateNode[]).some((attribute) => {
        return api.ast.getDirectiveName(attribute) !== 'slot' || Boolean(attribute.value);
    });
}

/** Builder input of `flagSlotForManualMove`: a custom `hint`, or the prop the slot content moves to. */
export type FlagSlotForManualMoveConfig = {
    slot: string;
    fix?: FixLevel;
    message?: string;
} & ({ hint: string; toProp?: never } | { toProp: string; hint?: never });

/**
 * Keeps a slot whose content cannot move automatically and puts a review comment above it. Use it
 * when the content is markup that only a developer can turn into a prop or move elsewhere.
 *
 * @example
 * flagSlotForManualMove({ slot: 'default', toProp: 'options' });
 * // review comment: Remove the "default" slot and use the "options" prop instead
 * flagSlotForManualMove({ slot: 'content', hint: 'Set the content outside the component.' });
 */
export function flagSlotForManualMove({
    slot,
    toProp,
    hint,
    fix = 'unsafe-auto',
    message,
}: FlagSlotForManualMoveConfig): FlagSlotForManualMoveStep {
    return defineUsageStep<FlagSlotForManualMoveStep>(
        {
            kind: 'flag-slot-for-manual-move',
            slot,
            hint: hint ?? `Remove the "${slot}" slot and use the "${toProp}" prop instead`,
            fix,
            ...(message === undefined ? {} : { message }),
        },
        (api, step) => {
            const target = findSlotContent(api, step.slot);

            if (!target || api.ast.hasCodemodComment(api.node, step.hint)) {
                return;
            }

            reportUsage(api, {
                node: target,
                apiName: step.slot,
                fix: (fixer) => api.insertCodemodCommentBefore(fixer, target, step.hint),
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
