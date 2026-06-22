/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-return */
/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import { componentUsageMessage, usageFixesAutomatically } from '../shared';

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

                    const insertPosition = usageConfig.insertPosition === 'after-name' ? 'after-name' : 'before-end';
                    const startTagSource = api.sourceCode.getText(api.node.startTag);
                    const selfClosingEnd = startTagSource.match(/\s*\/>\s*$/);
                    const beforeEndOffset = selfClosingEnd
                        ? api.node.startTag.range[1] - selfClosingEnd[0].length
                        : api.node.startTag.range[1] - 1;
                    const insertOffset = insertPosition === 'after-name'
                        ? api.node.startTag.range[0] + `<${api.node.name}`.length
                        : beforeEndOffset;
                    const prefix = usageConfig.bind === true ? ':' : '';

                    return fixer.insertTextAfterRange(
                        [
                            insertOffset,
                            insertOffset,
                        ],
                        ` ${prefix}${usageConfig.prop}="${usageConfig.value}"`,
                    );
                },
            });
        },
    };
}
