/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access -- vue-eslint-parser template nodes and ESLint fixers are passed through as plain records */
/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, DeprecationUsage } from '../types';
import type { MissingPropConfig } from './index';
import { componentUsageMessage, findObjectVBindAttribute, usageFixesAutomatically } from '../shared';

/** The usage `missingProp` builds: its config with the `unlessProps` default filled in. */
export type MissingPropUsage = DeprecationUsage & MissingPropConfig & Required<Pick<MissingPropConfig, 'unlessProps'>>;

function getPropSource(usageConfig: MissingPropUsage): string {
    const prefix = usageConfig.bind === true ? ':' : '';

    return `${prefix}${usageConfig.prop}="${usageConfig.value}"`;
}

function objectVBindCanHideAlias(usageConfig: MissingPropUsage): boolean {
    return usageConfig.unlessProps.some((propName) => propName !== usageConfig.prop);
}

/**
 * Inserts the missing prop at the configured position. With an object v-bind it goes in front of
 * the bound object, so values in the object still win.
 */
function insertMissingProp(
    api: ComponentUsageRuleApi,
    fixer: Record<string, any>,
    usageConfig: MissingPropUsage,
    objectVBindAttribute: Record<string, any> | null,
): unknown {
    const propSource = getPropSource(usageConfig);

    if (objectVBindAttribute) {
        const attributeLine = objectVBindAttribute.loc?.start?.line;
        const startTagLine = api.node.startTag.loc?.start?.line;

        if (typeof attributeLine === 'number' && typeof startTagLine === 'number' && attributeLine > startTagLine) {
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
        insertPosition === 'after-name' ? api.node.startTag.range[0] + `<${api.node.name}`.length : beforeEndOffset;

    return fixer.insertTextAfterRange(
        [
            insertOffset,
            insertOffset,
        ],
        ` ${propSource}`,
    );
}

/**
 * Puts a codemod review comment above the element, asking the developer to confirm the added prop.
 */
function insertReviewHint(api: ComponentUsageRuleApi, fixer: Record<string, any>, reviewHint: string): unknown {
    const column = api.node.loc?.start?.column;
    const indentation = ' '.repeat(typeof column === 'number' ? column : 0);

    return fixer.insertTextBeforeRange(
        [
            api.node.range[0],
            api.node.range[0],
        ],
        `<!-- TODO Codemod: ${reviewHint} -->\n${indentation}`,
    );
}

export function createMissingPropEslint(usageConfig: MissingPropUsage): DeprecationUsage['eslint'] {
    return {
        report(api) {
            const hasBlockingProp = usageConfig.unlessProps.some((propName) => {
                return api.ast.hasMatchingPropAttribute(api.node, propName);
            });

            if (hasBlockingProp) {
                return;
            }

            const objectVBindAttribute = findObjectVBindAttribute(api);
            const objectVBindAliasMessage =
                objectVBindAttribute && objectVBindCanHideAlias(usageConfig)
                    ? `Object v-bind can hide one of the fallback props. Review the bound object and add "${usageConfig.prop}" manually if needed.`
                    : undefined;
            const reportUsageConfig: DeprecationUsage = objectVBindAliasMessage
                ? {
                      ...usageConfig,
                      fix: 'manual',
                      message: objectVBindAliasMessage,
                  }
                : usageConfig;

            api.reportWithDuplicateReplacementGuard({
                node: api.node,
                message: componentUsageMessage(api, reportUsageConfig, usageConfig.prop),
                fix(fixer: Record<string, any>) {
                    if (!usageFixesAutomatically(api, reportUsageConfig)) {
                        return null;
                    }

                    const propFix = insertMissingProp(api, fixer, usageConfig, objectVBindAttribute);

                    if (typeof usageConfig.reviewHint !== 'string') {
                        return propFix;
                    }

                    return [
                        propFix,
                        insertReviewHint(api, fixer, usageConfig.reviewHint),
                    ];
                },
            });
        },
    };
}
