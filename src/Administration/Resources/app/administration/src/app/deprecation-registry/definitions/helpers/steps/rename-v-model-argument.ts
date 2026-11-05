/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-argument -- vue-eslint-parser template nodes are untyped records */
/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, RenameVModelArgumentStep, StepConfig, TemplateNode } from '../types';
import { defineUsageStep, hasObjectVBind, reportUsage } from '../shared';

/**
 * Renames the argument of a `v-model`, keeping its modifiers.
 *
 * - `v-model:from` → `v-model:to`; `to: null` (the default) → a plain `v-model`
 * - `from: null` → matches a plain `v-model`
 * - an object `v-bind` on the element → manual
 *
 * @example
 * renameVModelArgument({ from: 'value', to: null });
 * // <mt-x v-model:value.trim="x" /> → <mt-x v-model.trim="x" />
 */
export function renameVModelArgument({
    fix = 'auto',
    ...config
}: StepConfig<RenameVModelArgumentStep>): RenameVModelArgumentStep {
    return defineUsageStep<RenameVModelArgumentStep>(
        { kind: 'rename-v-model-argument', fix, ...config },
        reportRenameVModelArgument,
    );
}

function reportRenameVModelArgument(api: ComponentUsageRuleApi, step: RenameVModelArgumentStep): void {
    const attribute = api.ast.findMatchingVModelAttribute(api.node, step.from);

    if (!attribute) {
        return;
    }

    const to = step.to ?? null;
    const binding = (argument: string | null) => (argument === null ? 'v-model' : `v-model:${argument}`);

    reportUsage(api, {
        node: attribute,
        apiName: binding(step.from),
        headline: `The "${binding(step.from)}" binding is deprecated. Use "${binding(to)}" instead.`,
        ...(hasObjectVBind(api)
            ? {
                  manualReason:
                      'Object v-bind can hide the replacement model prop. Review the bound object and rename this v-model manually if needed.',
              }
            : {
                  produces: { kind: 'model', name: binding(to) },
                  fix: (fixer) =>
                      to === null || !attribute.key.argument
                          ? fixer.replaceText(attribute.key, buildVModelKey(attribute.key, to))
                          : fixer.replaceText(attribute.key.argument, to),
              }),
    });
}

/**
 * Builds the source of a v-model directive key with the given argument, keeping the modifiers of the
 * original key, so a rewritten key does not drop `.trim`, `.lazy` or `.number`.
 *
 * @example
 * buildVModelKey(keyOf('v-model:value.trim'), null); // 'v-model.trim'
 * buildVModelKey(keyOf('v-model.number'), 'checked'); // 'v-model:checked.number'
 */
function buildVModelKey(key: TemplateNode, argument: string | null): string {
    const modifiers = (key.modifiers as TemplateNode[])
        .map((modifier) => `.${(modifier.rawName ?? modifier.name) as string}`)
        .join('');

    return argument === null ? `v-model${modifiers}` : `v-model:${argument}${modifiers}`;
}
