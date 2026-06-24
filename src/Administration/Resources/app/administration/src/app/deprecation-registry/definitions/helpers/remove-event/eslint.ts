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

export function createRemoveEventEslint(usageConfig: DeprecationUsage): DeprecationUsage['eslint'] {
    return {
        report(api) {
            if (typeof usageConfig.event !== 'string') {
                return;
            }

            const attribute = api.ast.findMatchingEventAttribute(api.node, usageConfig.event);

            if (!attribute) {
                return;
            }

            const objectVOnMessage = hasObjectVOn(api)
                ? `Object v-on can hide this event. Review the bound listener object and remove "${usageConfig.event}" manually if needed.`
                : undefined;

            api.reportWithDuplicateReplacementGuard({
                node: attribute,
                message: componentUsageMessage(api, usageConfig, usageConfig.event, objectVOnMessage),
                fix(fixer: Record<string, any>) {
                    if (!usageFixesAutomatically(api, usageConfig)) {
                        return null;
                    }

                    if (objectVOnMessage) {
                        return null;
                    }

                    return fixer.remove(attribute);
                },
            });
        },
    };
}
