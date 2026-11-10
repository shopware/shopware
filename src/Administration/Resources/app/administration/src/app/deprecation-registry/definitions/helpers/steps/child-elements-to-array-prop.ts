/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-argument -- vue-eslint-parser template nodes are untyped records */
/**
 * @sw-package framework
 */

import type {
    ChildElementsToArrayPropStep,
    ComponentUsageRuleApi,
    Fixer,
    ItemFieldSource,
    StepConfig,
    TemplateNode,
} from '../types';
import type { UsagePlan } from '../shared';
import { defineUsageStep, reportUsage } from '../shared';
import { isConditionalOrScopedSlot } from './slots';

/** One item element turned into code: an object literal, or the spread of a mapped `v-for` list. */
type ItemCode = {
    code: string;
    /** The list and its entries' keys of a `v-for` item whose fields are plain keys of the entries. */
    list?: { source: string; keys: Record<string, string> };
    reviews: string[];
};

/** Why an item or the slot cannot be converted. */
type Blocker = { manualReason: string };

const IDENTIFIER = /^[A-Za-z_$][\w$]*$/;

// A static value `asNumber` writes as it is
const NUMBER = /^-?\d+(\.\d+)?$/;

// Output longer than this is written with one item per line
const MAX_INLINE_LENGTH = 80;

/**
 * Turns child elements into an array prop: each `childElement` becomes one array item, whose fields
 * `eachItem` reads with `fromText()` or `fromAttribute()`. The children are removed.
 *
 * - static children → an array literal
 * - a `v-for` child → its list mapped into items
 * - a single `v-for` child whose fields are plain keys of the entries, with `forBoundList` → the list as it is
 * - every `v-for` list → review comment, because the list has to be an array
 * - a child without a `required` attribute, with another attribute, markup or Twig, other content
 *   next to the children, a conditional slot, or `arrayProp` already set → manual
 * - an `asNumber` attribute with a static value that is no number → manual
 *
 * @example
 * childElementsToArrayProp({
 *     childElement: 'option',
 *     arrayProp: 'options',
 *     eachItem: {
 *         value: fromAttribute('value', { required: true }),
 *         label: fromText(),
 *     },
 *     forBoundList: { value: 'value-property', label: 'label-property' },
 * });
 * // <mt-select><option value="a">A</option></mt-select> → <mt-select :options="[{ value: 'a', label: 'A' }]" />
 * // <option v-for="tag in tags" :value="tag.key">{{ tag.name }}</option>
 * // → :options="tags" value-property="key" label-property="name"
 * // <option v-for="tag in tags" :value="tag.key">{{ $t(tag.name) }}</option>
 * // → :options="tags.map((tag) => ({ value: tag.key, label: $t(tag.name) }))"
 * // <option>No value</option> → manual: "value" is required
 */
export function childElementsToArrayProp({
    fix = 'auto',
    ...config
}: StepConfig<ChildElementsToArrayPropStep>): ChildElementsToArrayPropStep {
    return defineUsageStep<ChildElementsToArrayPropStep>(
        { kind: 'child-elements-to-array-prop', fix, ...config },
        (api, step) => {
            const slotName = step.inSlot ?? 'default';
            const slot = api.ast.findSlot(api.node, slotName);
            const container = slot ?? (slotName === 'default' ? api.node : null);
            const content = ((container?.children ?? []) as TemplateNode[]).filter((child) => {
                const isSlotTemplate = !slot && child.type === 'VElement' && child.name === 'template';

                return !isSlotTemplate && !isWhitespace(child);
            });
            const items = content.filter((child) => isItem(step, child));

            if (content.length === 0) {
                return;
            }

            reportUsage(api, {
                node: slot ?? items[0] ?? content[0],
                apiName: slotName,
                headline: `The "${slotName}" slot is removed. Pass its <${step.childElement}> elements as the "${step.arrayProp}" prop.`,
                ...planItemsProp(api, step, slot, { items, otherContentCount: content.length - items.length }),
            });
        },
    );
}

function isItem(step: ChildElementsToArrayPropStep, node: TemplateNode): boolean {
    return node.type === 'VElement' && node.name === step.childElement;
}

function isWhitespace(node: TemplateNode): boolean {
    return node.type === 'VText' && (node.value as string).trim() === '';
}

function planItemsProp(
    api: ComponentUsageRuleApi,
    step: ChildElementsToArrayPropStep,
    slot: TemplateNode | undefined,
    { items, otherContentCount }: { items: TemplateNode[]; otherContentCount: number },
): UsagePlan {
    const manualMove = `Move the <${step.childElement}> elements into the "${step.arrayProp}" prop manually.`;
    const blocker =
        otherContentCount > 0
            ? { manualReason: `The slot holds other content than <${step.childElement}> elements.` }
            : findSlotBlocker(api, step, slot, items);

    if (blocker) {
        return { manualReason: `${blocker.manualReason} ${manualMove}` };
    }

    const itemCodes: ItemCode[] = [];

    for (const item of items) {
        const itemCode = readItem(api, step, item);

        if ('manualReason' in itemCode) {
            return { manualReason: `${itemCode.manualReason} ${manualMove}` };
        }

        itemCodes.push(itemCode);
    }

    // Static text is written with &quot;, so a double quote can only come from an expression
    if (itemCodes.some((itemCode) => itemCode.code.includes('"'))) {
        return {
            manualReason: `An item uses double quotes, which the "${step.arrayProp}" attribute cannot hold. ${manualMove}`,
        };
    }

    const attributes = buildAttributes(api, step, itemCodes);
    const reviews = [...new Set(itemCodes.flatMap((itemCode) => itemCode.reviews))];

    return {
        fix: (fixer: Fixer) => [
            insertAttributes(api, fixer, attributes),
            ...removeItems(api, fixer, slot, items),
        ],
        ...(reviews.length > 0 ? { reviewReason: reviews.join(' ') } : {}),
    };
}

/**
 * Finds why the slot as a whole cannot be converted: the prop is already set, the slot is conditional
 * or scoped, it holds other content than items, or Twig sits between the items.
 */
function findSlotBlocker(
    api: ComponentUsageRuleApi,
    step: ChildElementsToArrayPropStep,
    slot: TemplateNode | undefined,
    items: TemplateNode[],
): Blocker | undefined {
    if (api.ast.hasMatchingPropAttribute(api.node, step.arrayProp)) {
        return { manualReason: `The element already sets "${step.arrayProp}".` };
    }

    const itemsSource = api.sourceCode.text.slice(items[0].range[0], items[items.length - 1].range[1]);

    if (slot && isConditionalOrScopedSlot(api, slot) && !isUnusedSlotScope(api, slot, itemsSource)) {
        return { manualReason: 'The slot is rendered conditionally or its items use slot props.' };
    }

    if (/{%|%}|{#|#}/.test(itemsSource)) {
        return { manualReason: 'The items contain Twig, which a prop cannot hold.' };
    }

    return undefined;
}

/**
 * Tells whether the slot only declares slot props that its items do not use, like
 * `<template #default="{ active }">` around items that never read `active`. Such a slot moves like a
 * plain one; a slot with any other directive, such as `v-if`, does not.
 */
function isUnusedSlotScope(api: ComponentUsageRuleApi, slot: TemplateNode, itemsSource: string): boolean {
    const attributes = slot.startTag.attributes as TemplateNode[];
    const slotDirective = attributes.find((attribute) => api.ast.getDirectiveName(attribute) === 'slot');

    if (attributes.length !== 1 || !slotDirective?.value) {
        return false;
    }

    const scope = api.sourceCode.getText(slotDirective.value.expression ?? slotDirective.value);
    const names = scope.match(/[A-Za-z_$][\w$]*/g) ?? [];

    return names.every((name) => !new RegExp(`(^|[^\\w$.])${name.replace(/\$/g, '\\$')}([^\\w$]|$)`).test(itemsSource));
}

/**
 * Turns one item element into code, or returns why it needs a manual move.
 *
 * @example
 * readItem(api, step, optionOf('<option value="a">A</option>')); // { code: "{ value: 'a', label: 'A' }", reviews: [] }
 */
function readItem(api: ComponentUsageRuleApi, step: ChildElementsToArrayPropStep, item: TemplateNode): ItemCode | Blocker {
    const vFor = (item.startTag.attributes as TemplateNode[]).find((attribute) => {
        return api.ast.getDirectiveName(attribute) === 'for';
    });
    const unknownAttribute = findUnknownAttribute(api, step, item, Boolean(vFor));

    if (unknownAttribute) {
        return {
            manualReason: `A <${step.childElement}> element has "${api.sourceCode.getText(unknownAttribute.key)}", which an item of "${step.arrayProp}" cannot hold.`,
        };
    }

    const fields: Record<string, string> = {};
    const reviews: string[] = [];

    for (const [field, source] of Object.entries(step.eachItem)) {
        const value = readField(api, step, item, source);

        if (typeof value === 'object') {
            return value;
        }

        if (value !== undefined) {
            fields[field] = value;
        }

        if (source.from === 'attribute' && source.boundReview && isBound(api, item, source.attribute)) {
            reviews.push(source.boundReview);
        }
    }

    const objectCode = `{ ${Object.entries(fields)
        .map(([field, value]) => `${IDENTIFIER.test(field) ? field : `'${field}'`}: ${value}`)
        .join(', ')} }`;

    return vFor ? mapList(api, step, vFor, { fields, objectCode, reviews }) : { code: objectCode, reviews };
}

function findUnknownAttribute(
    api: ComponentUsageRuleApi,
    step: ChildElementsToArrayPropStep,
    item: TemplateNode,
    hasVFor: boolean,
): TemplateNode | undefined {
    const fieldAttributes = Object.values(step.eachItem).flatMap((source) =>
        source.from === 'attribute' ? [source.attribute] : [],
    );

    return (item.startTag.attributes as TemplateNode[]).find((attribute) => {
        const directive = api.ast.getDirectiveName(attribute);
        const name =
            directive === 'bind' ? api.ast.getDirectiveArgumentName(attribute) : api.ast.getStaticAttributeName(attribute);

        if (directive === 'for' || (hasVFor && directive === 'bind' && name === 'key')) {
            return false;
        }

        const isField = (directive === null || directive === 'bind') && name !== null;

        return (
            !isField ||
            !fieldAttributes.some((fieldAttribute) => api.ast.findMatchingPropAttribute(item, fieldAttribute) === attribute)
        );
    });
}

function isBound(api: ComponentUsageRuleApi, item: TemplateNode, attributeName: string): boolean {
    const attribute = api.ast.findMatchingPropAttribute(item, attributeName);

    return attribute !== undefined && api.ast.getDirectiveName(attribute) === 'bind';
}

/**
 * Reads one field of an item as JavaScript code. Returns undefined for an optional attribute the item
 * does not set, and a blocker when the value cannot become code.
 */
function readField(
    api: ComponentUsageRuleApi,
    step: ChildElementsToArrayPropStep,
    item: TemplateNode,
    source: ItemFieldSource,
): string | Blocker | undefined {
    if (source.from === 'text') {
        const value = api.ast.getSlotContentValue(item);

        if (value === null) {
            return {
                manualReason: `A <${step.childElement}> element contains markup, which an item of "${step.arrayProp}" cannot hold.`,
            };
        }

        if (value.kind === 'none') {
            return "''";
        }

        return value.kind === 'static' ? toStringLiteral(value.text) : value.code;
    }

    const attribute = api.ast.findMatchingPropAttribute(item, source.attribute);

    if (!attribute) {
        return source.required
            ? { manualReason: `A <${step.childElement}> element has no "${source.attribute}", which every item needs.` }
            : undefined;
    }

    if (api.ast.getDirectiveName(attribute) === 'bind') {
        return api.ast.getAttributeValueSource(attribute) ?? 'undefined';
    }

    // A valueless attribute such as `disabled` is set
    if (!attribute.value) {
        return 'true';
    }

    const text = attribute.value.value as string;

    if (text === '' && source.emptyAsNull) {
        return 'null';
    }

    if (source.asNumber) {
        return NUMBER.test(text)
            ? text
            : {
                  manualReason: `A <${step.childElement}> element has the ${source.attribute} "${text}", which is no number.`,
              };
    }

    return toStringLiteral(text);
}

/**
 * Turns a `v-for` item into the mapped list, or returns why it cannot be mapped. A list whose fields
 * are plain keys of its entries keeps them as `list`, so the prop can take the list as it is.
 *
 * @example
 * mapList(api, step, vFor, { fields: { label: 'tag.name' }, objectCode: '{ label: tag.name }', reviews: [] });
 * // { code: '...tags.map((tag) => ({ label: tag.name }))', list: { source: 'tags', keys: { label: 'name' } }, … }
 */
function mapList(
    api: ComponentUsageRuleApi,
    step: ChildElementsToArrayPropStep,
    vFor: TemplateNode,
    item: { fields: Record<string, string>; objectCode: string; reviews: string[] },
): ItemCode | Blocker {
    const { fields, objectCode, reviews } = item;
    const expression = vFor.value?.expression as TemplateNode | undefined;
    const aliases = (expression?.left ?? []) as TemplateNode[];

    if (!expression || aliases.length === 0 || aliases.length > 2 || aliases.some((alias) => alias?.type !== 'Identifier')) {
        return { manualReason: `A <${step.childElement}> element iterates over an object or with a destructured entry.` };
    }

    if (expression.right.type === 'Literal') {
        return { manualReason: `A <${step.childElement}> element iterates over a number.` };
    }

    const source = api.sourceCode.getText(expression.right);
    const parameters = aliases.map((alias) => alias.name as string).join(', ');
    const entry = aliases[0].name as string;
    const keys = Object.fromEntries(
        Object.entries(fields).map(([field, code]) => [
            field,
            code.match(new RegExp(`^${entry}\\.([A-Za-z_$][\\w$]*)$`))?.[1],
        ]),
    );
    const isPlainList = Object.keys(step.eachItem).every((field) => keys[field] !== undefined);

    return {
        code: `...${source}.map((${parameters}) => (${objectCode}))`,
        ...(isPlainList ? { list: { source, keys: keys as Record<string, string> } } : {}),
        reviews: [
            ...(expression.right.type === 'ArrayExpression'
                ? []
                : [`The items come from "${source}", which has to be an array.`]),
            ...reviews,
        ],
    };
}

/**
 * Builds the attributes that replace the slot: the prop with the items, plus the key props when a
 * single plain list can be passed as it is.
 *
 * @example
 * buildAttributes(api, step, [listItem]); // [':options="tags"', 'value-property="key"', 'label-property="name"']
 */
function buildAttributes(api: ComponentUsageRuleApi, step: ChildElementsToArrayPropStep, itemCodes: ItemCode[]): string[] {
    const [onlyItem] = itemCodes;
    const keyProps = step.forBoundList;

    if (
        itemCodes.length === 1 &&
        onlyItem.list &&
        keyProps &&
        Object.keys(step.eachItem).every((field) => keyProps[field])
    ) {
        const { source, keys } = onlyItem.list;
        const keyAttributes = Object.entries(keys)
            .filter(([field, key]) => field !== key)
            .map(([field, key]) => `${keyProps[field]}="${key}"`);

        return [
            `:${step.arrayProp}="${source}"`,
            ...keyAttributes,
        ];
    }

    if (itemCodes.length === 1 && onlyItem.code.startsWith('...')) {
        return [`:${step.arrayProp}="${onlyItem.code.slice(3)}"`];
    }

    return [
        `:${step.arrayProp}="${formatArray(
            api,
            itemCodes.map((itemCode) => itemCode.code),
        )}"`,
    ];
}

/**
 * Writes the items as an array literal: on one line when it is short, otherwise one item per line,
 * indented one level deeper than the element.
 */
function formatArray(api: ComponentUsageRuleApi, codes: string[]): string {
    const inline = `[${codes.join(', ')}]`;

    if (inline.length <= MAX_INLINE_LENGTH) {
        return inline;
    }

    const indentation = ' '.repeat(getAttributeColumn(api));

    return `[\n${codes.map((code) => `${indentation}    ${code},`).join('\n')}\n${indentation}]`;
}

/**
 * The column new attributes start at: the column of the first attribute when the start tag spreads
 * its attributes over several lines, otherwise the column of the element.
 */
function getAttributeColumn(api: ComponentUsageRuleApi): number {
    const [firstAttribute] = api.node.startTag.attributes as TemplateNode[];
    const isMultiline = firstAttribute && firstAttribute.loc.start.line !== api.node.loc.start.line;

    return (isMultiline ? firstAttribute.loc.start.column : api.node.loc.start.column) as number;
}

function insertAttributes(api: ComponentUsageRuleApi, fixer: Fixer, attributes: string[]) {
    const [firstAttribute] = api.node.startTag.attributes as TemplateNode[];
    const tagNameEnd = (api.node.startTag.range[0] as number) + 1 + (api.node.rawName as string).length;
    const separator =
        firstAttribute && firstAttribute.loc.start.line !== api.node.loc.start.line
            ? `\n${' '.repeat(getAttributeColumn(api))}`
            : ' ';

    return fixer.insertTextAfterRange(
        [tagNameEnd, tagNameEnd],
        attributes.map((attribute) => `${separator}${attribute}`).join(''),
    );
}

/**
 * Removes the slot: its template, or the items of a default slot. An element that holds nothing else
 * becomes self-closing.
 */
function removeItems(api: ComponentUsageRuleApi, fixer: Fixer, slot: TemplateNode | undefined, items: TemplateNode[]) {
    const removed = slot ? [slot] : items;
    const holdsNothingElse = (api.node.children as TemplateNode[]).every((child) => {
        return removed.includes(child) || isWhitespace(child);
    });

    if (holdsNothingElse && api.node.endTag) {
        const startTagEnd = api.node.startTag.range[1] as number;
        const beforeClose = api.sourceCode.text[startTagEnd - 2];

        return [
            fixer.replaceTextRange([startTagEnd - 1, api.node.range[1] as number], /\s/.test(beforeClose) ? '/>' : ' />'),
            ...api.keepCommentsOf(fixer, [[startTagEnd, api.node.endTag.range[0] as number]]),
        ];
    }

    return [
        ...removed.map((node) => fixer.removeRange(api.ast.getRemovalRange(node))),
        ...api.keepCommentsOf(
            fixer,
            removed.map((node) => node.range as [number, number]),
        ),
    ];
}

/**
 * Writes text as a single-quoted JavaScript string that fits into a double-quoted attribute.
 *
 * @example
 * toStringLiteral('It\'s "new"'); // 'It\'s &quot;new&quot;'
 */
function toStringLiteral(text: string): string {
    const escaped = text
        .replace(/\s+/g, ' ')
        .trim()
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;');

    return `'${escaped}'`;
}
