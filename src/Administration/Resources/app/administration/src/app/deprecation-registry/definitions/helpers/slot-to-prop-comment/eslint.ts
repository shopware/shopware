/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-return -- vue-eslint-parser template nodes and ESLint fixers are passed through as plain records */
/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import type { SlotToPropCommentConfig } from './index';
import { componentUsageMessage, usageFixesAutomatically } from '../shared';

type SlotToPropCommentUsage = DeprecationUsage & SlotToPropCommentConfig;

export function createSlotToPropCommentEslint(usageConfig: SlotToPropCommentUsage): DeprecationUsage['eslint'] {
    return {
        report(api) {
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
