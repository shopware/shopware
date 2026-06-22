/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-return */
/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import { componentUsageMessage, usageFixesAutomatically } from '../shared';

export function createRenameEventEslint(usageConfig: DeprecationUsage): DeprecationUsage['eslint'] {
    return {
        report(api) {
            if (typeof usageConfig.from !== 'string' || typeof usageConfig.to !== 'string') {
                return;
            }

            const attribute = api.ast.findMatchingEventAttribute(api.node, usageConfig.from);

            if (!attribute) {
                return;
            }

            api.reportWithDuplicateReplacementGuard({
                node: attribute,
                message: componentUsageMessage(api, usageConfig, usageConfig.from),
                fix(fixer: Record<string, any>) {
                    if (!usageFixesAutomatically(api, usageConfig)) {
                        return null;
                    }

                    return fixer.replaceText(attribute.key.argument, usageConfig.to);
                },
            });
        },
    };
}
