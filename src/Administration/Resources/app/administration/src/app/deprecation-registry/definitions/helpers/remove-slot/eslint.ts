/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-return, @typescript-eslint/no-unsafe-argument */
/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import { componentUsageMessage, usageFixesAutomatically } from '../shared';

export function createRemoveSlotEslint(usageConfig: DeprecationUsage): DeprecationUsage['eslint'] {
    return {
        report(api) {
            if (typeof usageConfig.slot !== 'string') {
                return;
            }

            const slot = usageConfig.slot;
            const slotNode = api.ast.findSlot(api.node, slot);

            if (!slotNode) {
                return;
            }

            if (
                api.node.name === 'mt-tabs' &&
                slot === 'content' &&
                api.ast.hasCodemodComment(api.node, 'The "content" slot is not used anymore')
            ) {
                return;
            }

            api.reportWithDuplicateReplacementGuard({
                node: slotNode,
                message: componentUsageMessage(api, usageConfig, slot),
                fix(fixer: Record<string, any>) {
                    if (!usageFixesAutomatically(api, usageConfig)) {
                        return null;
                    }

                    if (api.node.name === 'mt-tabs' && slot === 'content') {
                        const indentation = ' '.repeat(slotNode.startTag?.loc?.start?.column ?? 0);

                        return fixer.insertTextBeforeRange(
                            slotNode.startTag.range,
                            `<!-- TODO Codemod: The "content" slot is not used anymore. Please set the content manually outside the component. -->\n${indentation}`,
                        );
                    }

                    if (slot === 'actions') {
                        return fixer.replaceText(slotNode, `<!-- Slot "actions" was removed and has no replacement. -->`);
                    }

                    if (api.node.name === 'mt-switch' && slot === 'hint') {
                        return fixer.replaceText(slotNode, `<!-- Slot "hint" was removed with no replacement. -->`);
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
