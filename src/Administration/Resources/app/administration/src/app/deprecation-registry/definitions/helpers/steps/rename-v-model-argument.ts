/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-argument -- vue-eslint-parser template nodes are untyped records */
/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, RenameVModelArgumentStep, StepConfig, TemplateNode } from '../types';
import { defineUsageStep, hasObjectVBind, reportUsage } from '../shared';

/**
 * Renames the argument of a `v-model`, keeping its modifiers; `to: null` (the default) turns it into a
 * plain `v-model`.
 *
 * @example
 * renameVModelArgument({ from: 'value', to: null }); // v-model:value.trim="x" → v-model.trim="x"
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

    reportUsage(api, {
        node: attribute,
        apiName: step.from ?? 'v-model',
        replacement: to ?? undefined,
        ...(hasObjectVBind(api)
            ? {
                  manualReason:
                      'Object v-bind can hide the replacement model prop. Review the bound object and rename this v-model manually if needed.',
              }
            : {
                  produces: { kind: 'model', name: to === null ? 'v-model' : `v-model:${to}` },
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
