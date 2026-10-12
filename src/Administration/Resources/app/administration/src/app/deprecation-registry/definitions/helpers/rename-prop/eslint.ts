/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-return -- vue-eslint-parser template nodes and ESLint fixers are passed through as plain records */
/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, DeprecationUsage } from '../types';
import type { RenamePropConfig } from './index';
import { componentUsageMessage, hasObjectVBind, usageFixesAutomatically } from '../shared';

type RenamePropUsage = DeprecationUsage & RenamePropConfig;

function hasConflictingDeprecatedProp(api: ComponentUsageRuleApi, usageConfig: RenamePropUsage): boolean {
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

function reportRenameProp(api: ComponentUsageRuleApi, usageConfig: RenamePropUsage): void {
    const attribute = api.ast.findMatchingPropAttribute(api.node, usageConfig.from);

    if (!attribute) {
        return;
    }

    const transform = api.getTransformResult(usageConfig, api.node, attribute);
    const objectVBindUsed = hasObjectVBind(api);
    const objectVBindMessage = objectVBindUsed
        ? `Object v-bind can hide the replacement prop. Review the bound object and rename "${usageConfig.from}" to "${usageConfig.to}" manually if needed.`
        : undefined;

    api.reportWithDuplicateReplacementGuard({
        node: attribute,
        message: componentUsageMessage(api, usageConfig, usageConfig.from, transform?.message ?? objectVBindMessage),
        fix(fixer: Record<string, any>) {
            if (!usageFixesAutomatically(api, usageConfig)) {
                return null;
            }

            if (transform?.fix === 'manual' || objectVBindUsed) {
                return null;
            }

            if (transform?.kind === 'replace-with-static-value') {
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

                if (api.ast.getDirectiveName(attribute) === 'bind') {
                    return fixer.replaceText(attribute, `:${usageConfig.to}="!(${value})"`);
                }

                return fixer.replaceText(attribute, `${usageConfig.to}="!(${value})"`);
            }

            if (api.ast.getDirectiveName(attribute) === 'bind') {
                return fixer.replaceText(attribute.key.argument, usageConfig.to);
            }

            return fixer.replaceText(attribute.key, usageConfig.to);
        },
    });
}

export function createRenamePropEslint(usageConfig: RenamePropUsage): DeprecationUsage['eslint'] {
    return {
        report: (api) => reportRenameProp(api, usageConfig),
    };
}
