/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-return, @typescript-eslint/no-unsafe-argument */
/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, DeprecationUsage } from '../types';
import { componentUsageMessage, usageFixesAutomatically } from '../shared';

function reportRemoveProp(api: ComponentUsageRuleApi, usageConfig: DeprecationUsage): void {
    if (typeof usageConfig.prop !== 'string') {
        return;
    }

    const attribute = api.ast.findMatchingPropAttribute(api.node, usageConfig.prop);

    if (!attribute) {
        return;
    }

    const transform = api.getTransformResult(usageConfig, api.node, attribute);
    const hasObjectVBind = api.node.startTag.attributes.some((startTagAttribute: Record<string, any>) => {
        return (
            api.ast.getDirectiveName(startTagAttribute) === 'bind' && !api.ast.getDirectiveArgumentName(startTagAttribute)
        );
    });
    const objectVBindMessage = hasObjectVBind
        ? `Object v-bind can hide this prop. Review the bound object and remove "${usageConfig.prop}" manually if needed.`
        : undefined;

    api.reportWithDuplicateReplacementGuard({
        node: attribute,
        message: componentUsageMessage(api, usageConfig, usageConfig.prop, transform?.message ?? objectVBindMessage),
        fix(fixer: Record<string, any>) {
            if (!usageFixesAutomatically(api, usageConfig)) {
                return null;
            }

            if (transform?.fix === 'manual' || hasObjectVBind) {
                return null;
            }

            if (transform?.kind === 'router-link-to-click') {
                const routerLinkValue =
                    api.ast.getDirectiveName(attribute) === 'bind'
                        ? api.ast.getAttributeValueSource(attribute)
                        : `'${api.ast.getAttributeValueSource(attribute)}'`;

                return fixer.replaceText(attribute, `@click="$router.push(${routerLinkValue})"`);
            }

            if (transform?.kind === 'ai-badge-to-title-slot') {
                const indent = api.node.startTag.loc.start.column + 4;
                const aiBadgeCondition =
                    api.ast.getDirectiveName(attribute) === 'bind'
                        ? ` v-if="${api.ast.getAttributeValueSource(attribute)}"`
                        : '';

                return [
                    fixer.remove(attribute),
                    fixer.insertTextAfter(
                        api.node.startTag,
                        `\n${' '.repeat(indent)}<slot name="title"><sw-ai-copilot-badge${aiBadgeCondition} /></slot>\n${' '.repeat(indent)}`,
                    ),
                ];
            }

            return fixer.remove(attribute);
        },
    });
}

export function createRemovePropEslint(usageConfig: DeprecationUsage): DeprecationUsage['eslint'] {
    return {
        report: (api) => reportRemoveProp(api, usageConfig),
    };
}
