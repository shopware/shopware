/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-return, @typescript-eslint/no-unsafe-argument */
/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, DeprecationUsage } from '../types';
import { componentUsageMessage, usageFixesAutomatically } from '../shared';

function buildTabsItems(api: ComponentUsageRuleApi, slotChildren: Array<Record<string, any>>): string {
    const items = slotChildren.map((child) => {
        const attributes = child.startTag.attributes;
        const nameAttribute = attributes.find((attribute: Record<string, any>) => api.ast.getStaticAttributeName(attribute) === 'name');
        const routeAttribute = attributes.find((attribute: Record<string, any>) => api.ast.getStaticAttributeName(attribute) === 'route');
        const routeAttributeExpression = attributes.find((attribute: Record<string, any>) => {
            return api.ast.getDirectiveName(attribute) === 'bind' && api.ast.getDirectiveArgumentName(attribute) === 'route';
        });
        const rawTextContent = child.children.find((itemChild: Record<string, any>) => itemChild.type === 'VText')?.value;
        const textContent = rawTextContent?.replace(/\n/g, '').trim();
        const rawLabel = textContent?.match(/\$tc\((.*)\)/)?.[1] ?? textContent?.match(/\$t\((.*)\)/)?.[1] ?? textContent;
        const label = rawLabel?.replace(/['"]+/g, '').trim();
        let name = nameAttribute?.value?.value ?? 'TODO: change this property';

        if (!nameAttribute && routeAttributeExpression) {
            name = api.sourceCode.text.slice(
                routeAttributeExpression.value.expression.range[0],
                routeAttributeExpression.value.expression.range[1],
            );
        } else if (!nameAttribute && routeAttribute) {
            name = routeAttribute.value.value;
        }

        return {
            label,
            name,
        };
    });

    return JSON.stringify(items, null, 4).replace(/[\/()']/g, "\\'").replace(/"/g, "'");
}

function reportTabsDefaultSlotToItems(api: ComponentUsageRuleApi, usageConfig: DeprecationUsage): void {
    const defaultSlot = api.ast.findSlot(api.node, 'default');
    const childWithoutSlot = api.ast.getFirstElementChildWithoutSlot(api.node);
    const target = defaultSlot ?? childWithoutSlot;

    if (!target || api.ast.hasMatchingPropAttribute(api.node, 'items')) {
        return;
    }

    api.reportWithDuplicateReplacementGuard({
        node: target,
        message: componentUsageMessage(api, usageConfig, usageConfig.name),
        fix(fixer: Record<string, any>) {
            if (!usageFixesAutomatically(api, usageConfig)) {
                return null;
            }

            const slotChildren = (defaultSlot?.children ?? api.node.children).filter((child: Record<string, any>) => {
                return child.type === 'VElement' && child.name === 'sw-tabs-item';
            });
            const rangeAfterStartTag = api.node.startTag?.range[0] + '<mt-tabs'.length;
            const fixes = [
                fixer.insertTextAfterRange([rangeAfterStartTag, rangeAfterStartTag], ` :items="${buildTabsItems(api, slotChildren)}"`),
            ];

            if (defaultSlot) {
                fixes.push(
                    fixer.insertTextBeforeRange(
                        defaultSlot.startTag.range,
                        `<!-- TODO Codemod: This slot is not used anymore. Please use the "items" property instead. -->\n`,
                    ),
                );
            } else {
                fixes.push(
                    fixer.insertTextBeforeRange(
                        api.node.children[0].range,
                        `<!-- TODO Codemod: This slot is not used anymore. Please use the "items" property instead. -->`,
                    ),
                );
            }

            return fixes;
        },
    });
}

function reportSelectSlotComment(api: ComponentUsageRuleApi, usageConfig: DeprecationUsage): void {
    const defaultSlot = api.ast.findSlot(api.node, 'default');
    const childWithoutSlot = api.ast.getFirstElementChildWithoutSlot(api.node);
    const target = defaultSlot ?? childWithoutSlot;
    const comment = 'Remove the "default" slot and use the "options" prop instead';

    if (!target || api.ast.hasCodemodComment(api.node, comment)) {
        return;
    }

    api.reportWithDuplicateReplacementGuard({
        node: target,
        message: componentUsageMessage(api, usageConfig, usageConfig.name),
        fix(fixer: Record<string, any>) {
            if (!usageFixesAutomatically(api, usageConfig)) {
                return null;
            }

            return fixer.insertTextBefore(target.startTag, `<!-- TODO Codemod: ${comment} -->\n`);
        },
    });
}

function reportButtonDefaultVariant(api: ComponentUsageRuleApi, usageConfig: DeprecationUsage): void {
    if (api.ast.hasMatchingPropAttribute(api.node, 'variant')) {
        return;
    }

    api.reportWithDuplicateReplacementGuard({
        node: api.node,
        message: componentUsageMessage(api, usageConfig, usageConfig.name),
        fix(fixer: Record<string, any>) {
            if (!usageFixesAutomatically(api, usageConfig)) {
                return null;
            }

            return fixer.insertTextAfterRange([api.node.startTag.range[0], api.node.startTag.range[1] - 1], ' variant="secondary"');
        },
    });
}

function reportFloatingUiDefaultOpened(api: ComponentUsageRuleApi, usageConfig: DeprecationUsage): void {
    if (api.ast.hasMatchingPropAttribute(api.node, 'is-opened') || api.ast.hasMatchingPropAttribute(api.node, 'open')) {
        return;
    }

    api.reportWithDuplicateReplacementGuard({
        node: api.node,
        message: componentUsageMessage(api, usageConfig, usageConfig.name),
        fix(fixer: Record<string, any>) {
            if (!usageFixesAutomatically(api, usageConfig)) {
                return null;
            }

            const rangeAfterStartTag = api.node.startTag?.range[0] + '<mt-floating-ui'.length;

            return fixer.insertTextAfterRange([rangeAfterStartTag, rangeAfterStartTag], ` :is-opened="true"`);
        },
    });
}

function reportCustomUsage(api: ComponentUsageRuleApi, usageConfig: DeprecationUsage): void {
    if (usageConfig.name === 'tabs-default-slot-to-items') {
        reportTabsDefaultSlotToItems(api, usageConfig);
        return;
    }

    if (usageConfig.name === 'select-options-name-id-to-label-value') {
        return;
    }

    if (usageConfig.name === 'select-default-option-slot-to-options') {
        reportSelectSlotComment(api, usageConfig);
        return;
    }

    if (usageConfig.name === 'button-default-variant-secondary') {
        reportButtonDefaultVariant(api, usageConfig);
        return;
    }

    if (usageConfig.name === 'floating-ui-default-opened') {
        reportFloatingUiDefaultOpened(api, usageConfig);
    }
}

export function createCustomUsageEslint(usageConfig: DeprecationUsage): DeprecationUsage['eslint'] {
    return {
        report: (api) => reportCustomUsage(api, usageConfig),
    };
}
