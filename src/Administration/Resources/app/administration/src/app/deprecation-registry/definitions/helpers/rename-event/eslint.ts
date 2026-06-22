/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-return */
/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import { componentUsageMessage, usageFixesAutomatically } from '../shared';

function hasObjectVOn(api: Parameters<NonNullable<DeprecationUsage['eslint']>['report']>[0]): boolean {
    return api.node.startTag.attributes.some((startTagAttribute: Record<string, any>) => {
        return api.ast.getDirectiveName(startTagAttribute) === 'on' && !api.ast.getDirectiveArgumentName(startTagAttribute);
    });
}

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

            const objectVOnMessage = hasObjectVOn(api)
                ? `Object v-on can hide the replacement event. Review the bound listener object and rename "${usageConfig.from}" to "${usageConfig.to}" manually if needed.`
                : undefined;

            api.reportWithDuplicateReplacementGuard({
                node: attribute,
                message: componentUsageMessage(api, usageConfig, usageConfig.from, objectVOnMessage),
                fix(fixer: Record<string, any>) {
                    if (!usageFixesAutomatically(api, usageConfig)) {
                        return null;
                    }

                    if (objectVOnMessage) {
                        return null;
                    }

                    return fixer.replaceText(attribute.key.argument, usageConfig.to);
                },
            });
        },
    };
}
