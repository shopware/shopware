/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-return */
/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, DeprecationUsage } from '../types';
import { componentUsageMessage, usageFixesAutomatically } from '../shared';

function findObjectVBindAttribute(api: ComponentUsageRuleApi): Record<string, any> | null {
    return (
        api.node.startTag.attributes.find((attribute: Record<string, any>) => {
            return api.ast.getDirectiveName(attribute) === 'bind' && !api.ast.getDirectiveArgumentName(attribute);
        }) ?? null
    );
}

function getPropSource(usageConfig: DeprecationUsage): string {
    const prefix = usageConfig.bind === true ? ':' : '';

    return `${prefix}${usageConfig.prop}="${usageConfig.value}"`;
}

export function createMissingPropEslint(usageConfig: DeprecationUsage): DeprecationUsage['eslint'] {
    return {
        report(api) {
            if (typeof usageConfig.prop !== 'string' || typeof usageConfig.value !== 'string') {
                return;
            }

            const unlessProps = Array.isArray(usageConfig.unlessProps) ? usageConfig.unlessProps : [usageConfig.prop];
            const hasBlockingProp = unlessProps.some((propName) => {
                return typeof propName === 'string' && api.ast.hasMatchingPropAttribute(api.node, propName);
            });

            if (hasBlockingProp) {
                return;
            }

            api.reportWithDuplicateReplacementGuard({
                node: api.node,
                message: componentUsageMessage(api, usageConfig, usageConfig.prop),
                fix(fixer: Record<string, any>) {
                    if (!usageFixesAutomatically(api, usageConfig)) {
                        return null;
                    }

                    const objectVBindAttribute = findObjectVBindAttribute(api);
                    const propSource = getPropSource(usageConfig);

                    if (objectVBindAttribute) {
                        const attributeLine = objectVBindAttribute.loc?.start?.line;
                        const startTagLine = api.node.startTag.loc?.start?.line;

                        if (
                            typeof attributeLine === 'number' &&
                            typeof startTagLine === 'number' &&
                            attributeLine > startTagLine
                        ) {
                            const column = objectVBindAttribute.loc?.start?.column;
                            const indentation = ' '.repeat(typeof column === 'number' ? column : 0);

                            return fixer.insertTextBefore(objectVBindAttribute, `${propSource}\n${indentation}`);
                        }

                        return fixer.insertTextBefore(objectVBindAttribute, `${propSource} `);
                    }

                    const insertPosition = usageConfig.insertPosition === 'after-name' ? 'after-name' : 'before-end';
                    const startTagSource = api.sourceCode.getText(api.node.startTag);
                    const selfClosingEnd = startTagSource.match(/\s*\/>\s*$/);
                    const beforeEndOffset = selfClosingEnd
                        ? api.node.startTag.range[1] - selfClosingEnd[0].length
                        : api.node.startTag.range[1] - 1;
                    const insertOffset =
                        insertPosition === 'after-name'
                            ? api.node.startTag.range[0] + `<${api.node.name}`.length
                            : beforeEndOffset;

                    return fixer.insertTextAfterRange(
                        [
                            insertOffset,
                            insertOffset,
                        ],
                        ` ${propSource}`,
                    );
                },
            });
        },
    };
}
