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
    SlotToPropStep,
    StepConfig,
    TemplateNode,
} from '../types';
import type { UsagePlan } from '../shared';
import { defineUsageStep, reportUsage } from '../shared';

/**
 * Handles a slot the new component no longer has. An empty slot is deleted. A slot with content is
 * reported for a manual move, because the new component does not render it and only a developer can
 * decide where it goes.
 *
 * @example
 * removeSlot({ slot: 'actions', hint: 'mt-banner has no "actions" slot. Render the actions next to the banner.' });
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
 * Both `#from` and `v-slot:from` are matched.
 *
 * @example
 * renameSlot({ from: 'header-right', to: 'headerRight' });
 * // <template #header-right="{ item }"> → <template #headerRight="{ item }">
 */
export function renameSlot({ fix = 'auto', ...config }: StepConfig<RenameSlotStep>): RenameSlotStep {
    return defineUsageStep<RenameSlotStep>({ kind: 'rename-slot', fix, ...config }, (api, step) => {
        const slotDirective = findSlotDirective(api, step.from);

        if (!slotDirective) {
            return;
        }

        reportUsage(api, {
            node: slotDirective,
            apiName: step.from,
            headline: `The "${step.from}" slot is renamed. Use "${step.to}" instead.`,
            ...(findSlotDirective(api, step.to)
                ? { manualReason: `The element already fills the "${step.to}" slot. Merge both slots manually.` }
                : { fix: (fixer) => fixer.replaceText(slotDirective.key.argument as TemplateNode, step.to) }),
        });
    });
}

/**
 * Finds the `v-slot` directive of a child template that fills the named slot. Slot names are compared
 * case-insensitively only, because the parser lowercases them and `header-right` and `headerRight`
 * are different slots.
 *
 * @example
 * findSlotDirective(api, 'headerRight'); // the #headerRight attribute of <template #headerRight>
 */
function findSlotDirective(api: ComponentUsageRuleApi, slotName: string): TemplateNode | undefined {
    const children = api.node.children as TemplateNode[];

    return children
        .filter((child) => child.type === 'VElement' && child.name === 'template')
        .flatMap((child) => child.startTag.attributes as TemplateNode[])
        .find((attribute) => {
            const argumentName = api.ast.getDirectiveArgumentName(attribute);

            return api.ast.getDirectiveName(attribute) === 'slot' && argumentName?.toLowerCase() === slotName.toLowerCase();
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
