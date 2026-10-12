/* eslint-disable @typescript-eslint/no-explicit-any, @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-return, @typescript-eslint/no-unsafe-argument -- vue-eslint-parser template nodes and ESLint fixers are passed through as plain records */
/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, DeprecationUsage } from '../types';
import type { SlotToItemsPropConfig } from './index';
import { componentUsageMessage, usageFixesAutomatically } from '../shared';

/** The usage `slotToItemsProp` builds: its config with the slot and item attribute defaults filled in. */
export type SlotToItemsPropUsage = DeprecationUsage &
    SlotToItemsPropConfig &
    Required<Pick<SlotToItemsPropConfig, 'slot' | 'itemNameProp' | 'itemRouteProp'>>;

function buildItems(
    api: ComponentUsageRuleApi,
    usageConfig: SlotToItemsPropUsage,
    slotChildren: Array<Record<string, any>>,
): string {
    const { itemNameProp, itemRouteProp } = usageConfig;
    const items = slotChildren.map((child) => {
        const attributes = child.startTag.attributes;
        const nameAttribute = attributes.find(
            (attribute: Record<string, any>) => api.ast.getStaticAttributeName(attribute) === itemNameProp,
        );
        const routeAttribute = attributes.find(
            (attribute: Record<string, any>) => api.ast.getStaticAttributeName(attribute) === itemRouteProp,
        );
        const routeAttributeExpression = attributes.find((attribute: Record<string, any>) => {
            return (
                api.ast.getDirectiveName(attribute) === 'bind' &&
                api.ast.getDirectiveArgumentName(attribute) === itemRouteProp
            );
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

    return JSON.stringify(items, null, 4)
        .replace(/[\/()']/g, "\\'")
        .replace(/"/g, "'");
}

export function createSlotToItemsPropEslint(usageConfig: SlotToItemsPropUsage): DeprecationUsage['eslint'] {
    return {
        report(api) {
            const defaultSlot = api.ast.findSlot(api.node, usageConfig.slot);
            const childWithoutSlot = api.ast.getFirstElementChildWithoutSlot(api.node);
            const target = defaultSlot ?? childWithoutSlot;

            if (!target || api.ast.hasMatchingPropAttribute(api.node, usageConfig.prop)) {
                return;
            }

            api.reportWithDuplicateReplacementGuard({
                node: target,
                message: componentUsageMessage(api, usageConfig, usageConfig.slot),
                fix(fixer: Record<string, any>) {
                    if (!usageFixesAutomatically(api, usageConfig)) {
                        return null;
                    }

                    const slotChildren = (defaultSlot?.children ?? api.node.children).filter(
                        (child: Record<string, any>) => {
                            return child.type === 'VElement' && child.name === usageConfig.itemComponent;
                        },
                    );
                    const rangeAfterComponentName = api.node.startTag.range[0] + `<${api.node.name}`.length;
                    const fixes = [
                        fixer.insertTextAfterRange(
                            [
                                rangeAfterComponentName,
                                rangeAfterComponentName,
                            ],
                            ` :${usageConfig.prop}="${buildItems(api, usageConfig, slotChildren)}"`,
                        ),
                    ];

                    if (defaultSlot) {
                        fixes.push(
                            fixer.insertTextBeforeRange(
                                defaultSlot.startTag.range,
                                `<!-- TODO Codemod: This slot is not used anymore. Please use the "${usageConfig.prop}" property instead. -->\n`,
                            ),
                        );
                    } else {
                        fixes.push(
                            fixer.insertTextBeforeRange(
                                api.node.children[0].range,
                                `<!-- TODO Codemod: This slot is not used anymore. Please use the "${usageConfig.prop}" property instead. -->`,
                            ),
                        );
                    }

                    return fixes;
                },
            });
        },
    };
}
