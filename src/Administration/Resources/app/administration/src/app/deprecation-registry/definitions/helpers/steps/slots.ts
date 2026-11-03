/**
 * @sw-package framework
 */

import type {
    ComponentUsageRuleApi,
    FixLevel,
    FlagSlotForManualMoveStep,
    RemoveSlotStep,
    SlotToPropStep,
    StepConfig,
    TemplateNode,
} from '../types';
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
 * Replaces a slot whose content moves to a prop with a comment that keeps the content, for the
 * developer to move it into the prop.
 *
 * @example
 * slotToProp({ slot: 'label', prop: 'label', fix: 'unsafe-auto' });
 */
export function slotToProp({ fix = 'auto', ...config }: StepConfig<SlotToPropStep>): SlotToPropStep {
    return defineUsageStep<SlotToPropStep>({ kind: 'slot-to-prop', fix, ...config }, (api, step) => {
        const slot = api.ast.findSlot(api.node, step.slot);

        if (!slot) {
            return;
        }

        const content = api.ast.getCondensedTextContent(slot);

        reportUsage(api, {
            node: slot,
            apiName: step.slot,
            fix: (fixer) =>
                fixer.replaceText(
                    slot,
                    `<!-- Slot "${step.slot}" was removed and should be replaced with "${step.prop}" prop. Previous value was: ${content} -->`,
                ),
        });
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
