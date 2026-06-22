/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-return */
/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, DeprecationUsage } from '../types';
import {
    componentUsageMessage,
    usageFixesAutomatically,
} from '../shared';

function hasConflictingDeprecatedProp(api: ComponentUsageRuleApi, usageConfig: DeprecationUsage): boolean {
    return api.migration.usage.some((candidate) => {
        return (
            candidate !== api.usage &&
            candidate.kind === 'rename-prop' &&
            candidate.to === usageConfig.to &&
            typeof candidate.from === 'string' &&
            api.ast.findMatchingPropAttribute(api.node, candidate.from)
        );
    });
}

function reportRenameProp(api: ComponentUsageRuleApi, usageConfig: DeprecationUsage): void {
    if (typeof usageConfig.from !== 'string') {
        return;
    }

    const attribute = api.ast.findMatchingPropAttribute(api.node, usageConfig.from);

    if (!attribute) {
        return;
    }

    const transform = api.getTransformResult(usageConfig, api.node, attribute);

    api.reportWithDuplicateReplacementGuard({
        node: attribute,
        message: componentUsageMessage(api, usageConfig, usageConfig.from, transform?.message),
        fix(fixer: Record<string, any>) {
            if (!usageFixesAutomatically(api, usageConfig)) {
                return null;
            }

            if (transform?.fix === 'manual') {
                return null;
            }

            if (transform?.kind === 'replace-with-static-value') {
                if (typeof usageConfig.to !== 'string') {
                    return null;
                }

                if (api.ast.hasMatchingPropAttribute(api.node, usageConfig.to)) {
                    return fixer.remove(attribute);
                }

                if (hasConflictingDeprecatedProp(api, usageConfig)) {
                    return null;
                }

                return fixer.replaceText(attribute, `${usageConfig.to}="${transform.value}"`);
            }

            if (transform?.kind === 'invert-boolean') {
                if (!attribute.value) {
                    return fixer.remove(attribute);
                }

                const value = api.ast.getAttributeValueSource(attribute);
                const replacementName = usageConfig.to;

                if (typeof replacementName !== 'string') {
                    return null;
                }

                if (api.ast.getDirectiveName(attribute) === 'bind') {
                    return fixer.replaceText(attribute, `:${replacementName}="!(${value})"`);
                }

                return fixer.replaceText(attribute, `${replacementName}="!(${value})"`);
            }

            if (typeof usageConfig.to !== 'string') {
                return null;
            }

            if (api.ast.getDirectiveName(attribute) === 'bind') {
                return fixer.replaceText(attribute.key.argument, usageConfig.to);
            }

            return fixer.replaceText(attribute.key, usageConfig.to);
        },
    });
}

export function createRenamePropEslint(usageConfig: DeprecationUsage): DeprecationUsage['eslint'] {
    return {
        report: (api) => reportRenameProp(api, usageConfig),
    };
}
