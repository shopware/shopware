/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-return -- vue-eslint-parser template nodes and ESLint fixers are passed through as plain records */
/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import { componentUsageMessage, hasObjectVBind, usageFixesAutomatically } from '../shared';

export function createRenameVModelArgumentEslint(usageConfig: DeprecationUsage): DeprecationUsage['eslint'] {
    return {
        report(api) {
            const from = typeof usageConfig.from === 'string' ? usageConfig.from : null;
            const attribute = api.ast.findMatchingVModelAttribute(api.node, from);

            if (!attribute) {
                return;
            }

            const objectVBindMessage = hasObjectVBind(api)
                ? `Object v-bind can hide the replacement model prop. Review the bound object and rename this v-model manually if needed.`
                : undefined;

            api.reportWithDuplicateReplacementGuard({
                node: attribute,
                message: componentUsageMessage(api, usageConfig, from, objectVBindMessage),
                fix(fixer: Record<string, any>) {
                    if (!usageFixesAutomatically(api, usageConfig)) {
                        return null;
                    }

                    if (objectVBindMessage) {
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
