/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-return */
/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import { componentUsageMessage, usageFixesAutomatically } from '../shared';

export function createSlotToPropCommentEslint(usageConfig: DeprecationUsage): DeprecationUsage['eslint'] {
    return {
        report(api) {
            if (typeof usageConfig.slot !== 'string' || typeof usageConfig.prop !== 'string') {
                return;
            }

            const slot = api.ast.findSlot(api.node, usageConfig.slot);
            const childWithoutSlot = api.ast.getFirstElementChildWithoutSlot(api.node);
            const target = slot ?? childWithoutSlot;
            const comment = `Remove the "${usageConfig.slot}" slot and use the "${usageConfig.prop}" prop instead`;

            if (!target || api.ast.hasCodemodComment(api.node, comment)) {
                return;
            }

            api.reportWithDuplicateReplacementGuard({
                node: target,
                message: componentUsageMessage(api, usageConfig, usageConfig.slot),
                fix(fixer: Record<string, any>) {
                    if (!usageFixesAutomatically(api, usageConfig)) {
                        return null;
                    }

                    return fixer.insertTextBefore(target.startTag, `<!-- TODO Codemod: ${comment} -->\n`);
                },
            });
        },
    };
}
