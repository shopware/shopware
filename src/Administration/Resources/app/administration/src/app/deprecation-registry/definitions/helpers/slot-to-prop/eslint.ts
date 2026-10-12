/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-return -- vue-eslint-parser template nodes and ESLint fixers are passed through as plain records */
/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import { componentUsageMessage, usageFixesAutomatically } from '../shared';

export function createSlotToPropEslint(usageConfig: DeprecationUsage): DeprecationUsage['eslint'] {
    return {
        report(api) {
            if (typeof usageConfig.slot !== 'string') {
                return;
            }

            const slotName = usageConfig.slot;
            const slot = api.ast.findSlot(api.node, slotName);

            if (!slot) {
                return;
            }

            const { slotReviewHint } = usageConfig;

            if (slotReviewHint && api.ast.hasCodemodComment(api.node, slotReviewHint)) {
                return;
            }

            api.reportWithDuplicateReplacementGuard({
                node: slot,
                message: componentUsageMessage(api, usageConfig, slotName),
                fix(fixer: Record<string, any>) {
                    if (!usageFixesAutomatically(api, usageConfig)) {
                        return null;
                    }

                    if (slotReviewHint) {
                        return fixer.insertTextBefore(slot.startTag, `<!-- TODO Codemod: ${slotReviewHint} -->\n`);
                    }

                    const slotValue = api.ast.getCondensedTextContent(slot);

                    return fixer.replaceText(
                        slot,
                        `<!-- Slot "${slotName}" was removed and should be replaced with "${usageConfig.prop}" prop. Previous value was: ${slotValue} -->`,
                    );
                },
            });
        },
    };
}
