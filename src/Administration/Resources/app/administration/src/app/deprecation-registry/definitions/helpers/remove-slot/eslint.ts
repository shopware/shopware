/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-return, @typescript-eslint/no-unsafe-argument -- vue-eslint-parser template nodes and ESLint fixers are passed through as plain records */
/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import type { RemoveSlotConfig } from './index';
import { componentUsageMessage, usageFixesAutomatically } from '../shared';

type RemoveSlotUsage = DeprecationUsage & RemoveSlotConfig;

export function createRemoveSlotEslint(usageConfig: RemoveSlotUsage): DeprecationUsage['eslint'] {
    return {
        report(api) {
            const slot = usageConfig.slot;
            const slotNode = api.ast.findSlot(api.node, slot);

            if (!slotNode) {
                return;
            }

            const { slotReviewHint, slotReplacementComment } = usageConfig;

            if (slotReviewHint && api.ast.hasCodemodComment(api.node, slotReviewHint)) {
                return;
            }

            api.reportWithDuplicateReplacementGuard({
                node: slotNode,
                message: componentUsageMessage(api, usageConfig, slot),
                fix(fixer: Record<string, any>) {
                    if (!usageFixesAutomatically(api, usageConfig)) {
                        return null;
                    }

                    if (slotReviewHint) {
                        const indentation = ' '.repeat(slotNode.startTag?.loc?.start?.column ?? 0);

                        return fixer.insertTextBeforeRange(
                            slotNode.startTag.range,
                            `<!-- TODO Codemod: ${slotReviewHint} -->\n${indentation}`,
                        );
                    }

                    if (slotReplacementComment) {
                        return fixer.replaceText(slotNode, `<!-- ${slotReplacementComment} -->`);
                    }

                    const slotValue = api.ast.getCondensedTextContent(slotNode);

                    return fixer.replaceText(
                        slotNode,
                        `<!-- Slot "${slot}" was removed without replacement. Previous value was: ${slotValue} -->`,
                    );
                },
            });
        },
    };
}
