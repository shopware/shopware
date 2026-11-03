/* eslint-disable @typescript-eslint/no-unsafe-member-access -- vue-eslint-parser template nodes are untyped records */
/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, SlotToItemsPropStep, StepConfig, TemplateNode } from '../types';
import { defineUsageStep, reportUsage } from '../shared';
import { findSlotContent } from './slots';

type Item = {
    label: string | undefined;
    name: string;
};

/**
 * Turns the item elements of a slot into an items array prop, reading each item's name (or route)
 * and label, and flags the slot for removal.
 *
 * @example
 * slotToItemsProp({ prop: 'items', itemComponent: 'sw-tabs-item', fix: 'unsafe-auto' });
 * // <mt-tabs><sw-tabs-item name="a">A</sw-tabs-item></mt-tabs> → <mt-tabs :items="[{ 'label': 'A', 'name': 'a' }]">
 */
export function slotToItemsProp({ fix = 'unsafe-auto', ...config }: StepConfig<SlotToItemsPropStep>): SlotToItemsPropStep {
    return defineUsageStep<SlotToItemsPropStep>({ kind: 'slot-to-items-prop', fix, ...config }, reportSlotToItemsProp);
}

function reportSlotToItemsProp(api: ComponentUsageRuleApi, step: SlotToItemsPropStep): void {
    const slotName = step.slot ?? 'default';
    const target = findSlotContent(api, slotName);

    if (!target || api.ast.hasMatchingPropAttribute(api.node, step.prop)) {
        return;
    }

    const slot = api.ast.findSlot(api.node, slotName);
    const itemElements = ((slot ?? api.node).children as TemplateNode[]).filter((child) => {
        return child.type === 'VElement' && child.name === step.itemComponent;
    });
    const afterName = (api.node.startTag.range[0] as number) + `<${api.node.name}`.length;

    reportUsage(api, {
        node: target,
        apiName: slotName,
        produces: { kind: 'prop', name: step.prop },
        fix: (fixer) => [
            fixer.insertTextAfterRange(
                [
                    afterName,
                    afterName,
                ],
                ` :${step.prop}="${formatItems(itemElements.map((element) => readItem(api, step, element)))}"`,
            ),
            api.insertCodemodCommentBefore(
                fixer,
                target,
                `This slot is not used anymore. Please use the "${step.prop}" property instead.`,
            ),
        ],
    });
}

function readItem(api: ComponentUsageRuleApi, step: SlotToItemsPropStep, element: TemplateNode): Item {
    const itemNameProp = step.itemNameProp ?? 'name';
    const itemRouteProp = step.itemRouteProp ?? 'route';
    const attributes = element.startTag.attributes as TemplateNode[];
    const nameAttribute = attributes.find((attribute) => api.ast.getStaticAttributeName(attribute) === itemNameProp);
    const routeAttribute = attributes.find((attribute) => api.ast.getStaticAttributeName(attribute) === itemRouteProp);
    const boundRouteAttribute = attributes.find((attribute) => {
        return (
            api.ast.getDirectiveName(attribute) === 'bind' && api.ast.getDirectiveArgumentName(attribute) === itemRouteProp
        );
    });

    let name = (nameAttribute?.value?.value as string | undefined) ?? 'TODO: change this property';

    if (!nameAttribute && boundRouteAttribute) {
        const [start, end] = boundRouteAttribute.value.expression.range as [number, number];
        name = api.sourceCode.text.slice(start, end);
    } else if (!nameAttribute && routeAttribute) {
        name = routeAttribute.value.value as string;
    }

    return { label: readLabel(element), name };
}

/**
 * Reads an item's label from its text, unwrapping a `$tc('…')` or `$t('…')` call to the snippet key.
 *
 * @example
 * readLabel(elementOf("<sw-tabs-item>{{ $tc('sw-product.tab') }}</sw-tabs-item>")); // 'sw-product.tab'
 */
function readLabel(element: TemplateNode): string | undefined {
    const children = element.children as TemplateNode[];
    const text = (children.find((child) => child.type === 'VText')?.value as string | undefined)?.replace(/\n/g, '').trim();
    const label = text?.match(/\$tc\((.*)\)/)?.[1] ?? text?.match(/\$t\((.*)\)/)?.[1] ?? text;

    return label?.replace(/['"]+/g, '').trim();
}

/**
 * Formats the items as an indented array literal with single-quoted strings, which fits into the
 * double-quoted attribute the fix writes.
 *
 * @example
 * formatItems([{ label: "It's", name: 'a' }]);
 * // [\n    {\n        'label': 'It\'s',\n        'name': 'a'\n    }\n]
 */
function formatItems(items: Item[]): string {
    return JSON.stringify(items, null, 4).replace(/"(?:[^"\\]|\\.)*"/g, (jsonString) => {
        const value = JSON.parse(jsonString) as string;
        const escaped = value.replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');

        return `'${escaped}'`;
    });
}
