/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-return */
/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, DeprecationUsage } from '../types';
import { componentUsageMessage, usageFixesAutomatically } from '../shared';

function reportMapPropValue(api: ComponentUsageRuleApi, usageConfig: DeprecationUsage): void {
    if (typeof usageConfig.prop !== 'string') {
        return;
    }

    const attribute = api.ast.findMatchingPropAttribute(api.node, usageConfig.prop);

    if (!attribute || api.ast.getDirectiveName(attribute) === 'bind' || attribute.value?.value !== usageConfig.from) {
        return;
    }

    const transform = api.getTransformResult(usageConfig, api.node, attribute);

    api.reportWithDuplicateReplacementGuard({
        node: attribute,
        message: componentUsageMessage(api, usageConfig, usageConfig.prop, transform?.message),
        fix(fixer: Record<string, any>) {
            if (!usageFixesAutomatically(api, usageConfig)) {
                return null;
            }

            const fixes = [
                fixer.replaceText(attribute.value, `"${usageConfig.to}"`),
            ];

            if (transform?.fix === 'manual') {
                return null;
            }

            if (
                transform?.kind === 'add-boolean-prop' &&
                typeof transform.prop === 'string' &&
                !api.ast.hasMatchingPropAttribute(api.node, transform.prop)
            ) {
                fixes.push(fixer.insertTextAfterRange(attribute.range, ` ${transform.prop}`));
            }

            return fixes;
        },
    });
}

export function createMapPropValueEslint(usageConfig: DeprecationUsage): DeprecationUsage['eslint'] {
    return {
        report: (api) => reportMapPropValue(api, usageConfig),
    };
}
