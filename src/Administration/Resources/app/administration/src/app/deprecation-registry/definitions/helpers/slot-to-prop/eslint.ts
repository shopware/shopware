/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-return */
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

            const mtSelectComment = `Remove the "${slotName}" slot and use the "${usageConfig.prop}" prop instead`;

            if (api.node.name === 'mt-select' && api.ast.hasCodemodComment(api.node, mtSelectComment)) {
                return;
            }

            api.reportWithDuplicateReplacementGuard({
                node: slot,
                message: componentUsageMessage(api, usageConfig, slotName),
                fix(fixer: Record<string, any>) {
                    if (!usageFixesAutomatically(api, usageConfig)) {
                        return null;
                    }

                    const slotValue = api.ast.getCondensedTextContent(slot);

                    if (api.node.name === 'mt-select') {
                        return fixer.insertTextBefore(slot.startTag, `<!-- TODO Codemod: ${mtSelectComment} -->\n`);
                    }

                    return fixer.replaceText(
                        slot,
                        `<!-- Slot "${slotName}" was removed and should be replaced with "${usageConfig.prop}" prop. Previous value was: ${slotValue} -->`,
                    );
                },
            });
        },
    };
}
