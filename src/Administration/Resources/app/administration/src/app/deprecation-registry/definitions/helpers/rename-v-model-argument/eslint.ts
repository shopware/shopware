/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-return, @typescript-eslint/no-unsafe-argument -- vue-eslint-parser template nodes and ESLint fixers are passed through as plain records */
/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import type { RenameVModelArgumentConfig } from './index';
import { componentUsageMessage, hasObjectVBind, usageFixesAutomatically } from '../shared';

type RenameVModelArgumentUsage = DeprecationUsage & RenameVModelArgumentConfig;

/**
 * Builds the source of a v-model directive key with the given argument, keeping the modifiers of the
 * original key, so a rewritten key does not drop `.trim`, `.lazy` or `.number`.
 *
 * @example
 * buildVModelKey(keyOf('v-model:value.trim'), null); // 'v-model.trim'
 * buildVModelKey(keyOf('v-model.number'), 'checked'); // 'v-model:checked.number'
 */
function buildVModelKey(key: Record<string, any>, argument: string | null): string {
    const modifiers = (key.modifiers as Array<Record<string, any>>)
        .map((modifier) => `.${modifier.rawName ?? modifier.name}`)
        .join('');

    return argument === null ? `v-model${modifiers}` : `v-model:${argument}${modifiers}`;
}

export function createRenameVModelArgumentEslint(usageConfig: RenameVModelArgumentUsage): DeprecationUsage['eslint'] {
    return {
        report(api) {
            const attribute = api.ast.findMatchingVModelAttribute(api.node, usageConfig.from);

            if (!attribute) {
                return;
            }

            const objectVBindMessage = hasObjectVBind(api)
                ? `Object v-bind can hide the replacement model prop. Review the bound object and rename this v-model manually if needed.`
                : undefined;

            api.reportWithDuplicateReplacementGuard({
                node: attribute,
                message: componentUsageMessage(api, usageConfig, usageConfig.from, objectVBindMessage),
                fix(fixer: Record<string, any>) {
                    if (!usageFixesAutomatically(api, usageConfig)) {
                        return null;
                    }

                    if (objectVBindMessage) {
                        return null;
                    }

                    const to = usageConfig.to ?? null;

                    if (to === null || !attribute.key.argument) {
                        return fixer.replaceText(attribute.key, buildVModelKey(attribute.key, to));
                    }

                    return fixer.replaceText(attribute.key.argument, to);
                },
            });
        },
    };
}
