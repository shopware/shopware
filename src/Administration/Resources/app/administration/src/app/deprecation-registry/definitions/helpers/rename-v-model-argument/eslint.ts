/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-return */
/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import { componentUsageMessage, usageFixesAutomatically } from '../shared';

export function createRenameVModelArgumentEslint(usageConfig: DeprecationUsage): DeprecationUsage['eslint'] {
    return {
        report(api) {
            const from = typeof usageConfig.from === 'string' ? usageConfig.from : null;
            const attribute = api.ast.findMatchingVModelAttribute(api.node, from);

            if (!attribute) {
                return;
            }

            api.reportWithDuplicateReplacementGuard({
                node: attribute,
                message: componentUsageMessage(api, usageConfig, from),
                fix(fixer: Record<string, any>) {
                    if (!usageFixesAutomatically(api, usageConfig)) {
                        return null;
                    }

                    if (usageConfig.to === null) {
                        return fixer.replaceText(attribute.key, 'v-model');
                    }

                    if (typeof usageConfig.to !== 'string') {
                        return null;
                    }

                    if (!attribute.key.argument) {
                        return fixer.replaceText(attribute.key, `v-model:${usageConfig.to}`);
                    }

                    return fixer.replaceText(attribute.key.argument, usageConfig.to);
                },
            });
        },
    };
}
