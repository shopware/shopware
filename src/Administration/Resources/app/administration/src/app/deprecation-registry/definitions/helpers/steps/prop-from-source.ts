/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-argument -- vue-eslint-parser template nodes are untyped records */
/**
 * @sw-package framework
 */

import type {
    ComponentUsageRuleApi,
    Fix,
    Fixer,
    PropFromSourceStep,
    PropSource,
    PropValue,
    StepConfig,
    TemplateNode,
} from '../types';
import type { UsagePlan } from '../shared';
import { defineUsageStep, insertAttributeAtEnd, reportUsage } from '../shared';

type Value = Exclude<PropValue, { kind: 'none' }>;

/** A source the element uses, with the nodes the conversion removes. */
type FoundSource = {
    source: PropSource;
    /** The value the source passes; null when the slot is empty and the next source applies. */
    value: Value | null;
    /** The attribute of a prop source. */
    attribute?: TemplateNode;
    /** The slot nodes of a slot source. */
    slotNodes?: TemplateNode[];
    /** Attributes of the slot element besides the one read, which the prop cannot carry. */
    droppedAttributes?: string[];
};

// The parser drops Twig tags from text nodes, so they are only visible in the source
const TWIG = /{%|%}|{#|#}/;

// Calls that always return a string, so a bound value with them is never null or undefined
const STRING_FUNCTIONS = ['$t', '$tc'];

/**
 * Fills one prop of the new component from where the old component took that value. `source` is one
 * `fromSlot()` / `fromProp()`, or `firstFoundOf([...])` when the old component fell back from one to the
 * next. The used sources are removed; an element that already sets `toProp` keeps it.
 *
 * - a source found → its value
 * - nothing found → `oldComponentFallback`
 * - a bound source that can be null or undefined → review comment, because the old component showed
 *   `oldComponentFallback` then and the new one shows nothing
 * - `oldComponentToggle.prop` false → `valueWhenOff`; bound to an expression → a conditional value
 * - `plainText` with static HTML or a bound prop → review comment, because the new prop shows tags as text
 * - a slot element with more attributes than the one read, such as an icon's size → review comment
 * - a conditional slot, a slot with a dynamic name, Twig, or markup a prop cannot hold → manual
 *
 * @example
 * propFromSource({
 *     toProp: 'description',
 *     source: firstFoundOf([
 *         fromSlot('default'),
 *         fromProp('subline'),
 *     ]),
 *     oldComponentFallback: '$t($route.meta.$module.description)',
 *     oldComponentToggle: { prop: 'show-description', valueWhenOff: '' },
 * });
 * // <sw-empty-state>Hi</sw-empty-state> → <mt-empty-state description="Hi" />
 * // <sw-empty-state subline="Hi" /> → <mt-empty-state description="Hi" />
 * // <sw-empty-state /> → <mt-empty-state :description="$t($route.meta.$module.description)" />
 * // <sw-empty-state :subline="text" /> → <mt-empty-state :description="text" /> + review comment
 * // <sw-empty-state subline="Hi" :show-description="false" /> → <mt-empty-state description="" />
 * // <sw-empty-state subline="Hi" :show-description="isShown" /> → <mt-empty-state :description="isShown ? 'Hi' : ''" />
 *
 * propFromSource({
 *     toProp: 'icon',
 *     source: fromSlot('icon', { childElement: ['mt-icon', 'sw-icon'], attribute: 'name' }),
 * });
 * // <template #icon><mt-icon name="regular-rocket" /></template> → icon="regular-rocket"
 */
export function propFromSource({ fix = 'auto', ...config }: StepConfig<PropFromSourceStep>): PropFromSourceStep {
    return defineUsageStep<PropFromSourceStep>({ kind: 'prop-from-source', fix, ...config }, (api, step) => {
        const found = findSources(api, step);

        if ('manualReason' in found) {
            reportUsage(api, {
                node: api.node,
                apiName: step.toProp,
                headline: headline(step),
                manualReason: `${found.manualReason} Pass it as the "${step.toProp}" prop manually.`,
            });

            return;
        }

        const plan = planProp(api, step, found.sources);

        if (plan) {
            reportUsage(api, { node: api.node, apiName: step.toProp, headline: headline(step), ...plan });
        }
    });
}

/** The sources of a step in the order they are tried. */
function listSources(step: PropFromSourceStep): PropSource[] {
    return step.source.from === 'first-found-of' ? step.source.sources : [step.source];
}

function headline(step: PropFromSourceStep): string {
    const sources = listSources(step).map((source) => {
        if (source.from === 'prop') {
            return `the "${source.prop}" prop`;
        }

        return source.childElement
            ? `the <${source.childElement[0]}> in the "${source.slot}" slot`
            : `the "${source.slot}" slot`;
    });

    return `The "${step.toProp}" prop replaces ${sources.join(' and ')}.`;
}

/**
 * Collects the sources the element uses, in their order, or why one of them cannot become a value.
 */
function findSources(
    api: ComponentUsageRuleApi,
    step: PropFromSourceStep,
): { sources: FoundSource[] } | { manualReason: string } {
    const sources: FoundSource[] = [];
    const stepSources = listSources(step);
    const listsTarget = stepSources.some((source) => source.from === 'prop' && source.prop === step.toProp);
    // An element that already passes the new prop keeps it, the old sources come after it
    const orderedSources: PropSource[] = listsTarget ? stepSources : [{ from: 'prop', prop: step.toProp }, ...stepSources];
    const readsSlot = stepSources.some((source) => source.from === 'slot');

    if (readsSlot && hasDynamicSlot(api)) {
        return { manualReason: 'A slot with a dynamic name can fill one of its slots.' };
    }

    for (const source of orderedSources) {
        if (source.from === 'prop') {
            const attribute = api.ast.findMatchingPropAttribute(api.node, source.prop);

            if (attribute) {
                sources.push({ source, attribute, value: readAttribute(api, attribute) });
            }

            continue;
        }

        const slot = readSlot(api, source);

        if (slot && 'manualReason' in slot) {
            return slot;
        }

        if (slot) {
            sources.push(slot);
        }
    }

    return { sources };
}

function readAttribute(api: ComponentUsageRuleApi, attribute: TemplateNode): Value | null {
    if (api.ast.getDirectiveName(attribute) !== 'bind') {
        return { kind: 'static', text: (attribute.value?.value as string | undefined) ?? '' };
    }

    const code = api.ast.getAttributeValueSource(attribute);

    return code ? { kind: 'expr', code } : null;
}

/**
 * Reads a slot source: its template, or for the default slot also the content written without one.
 * An empty slot gives no value, because Vue renders the next source as the slot's fallback.
 */
function readSlot(
    api: ComponentUsageRuleApi,
    source: Extract<PropSource, { from: 'slot' }>,
): FoundSource | { manualReason: string } | null {
    const template = api.ast.findSlot(api.node, source.slot);
    const nodes = template
        ? (template.children as TemplateNode[])
        : source.slot === 'default'
          ? (api.node.children as TemplateNode[]).filter((child) => !isSlotTemplate(api, child))
          : [];
    const content = nodes.filter((child) => !(child.type === 'VText' && (child.value as string).trim() === ''));

    if (!template && content.length === 0) {
        return null;
    }

    const slotNodes = template ? [template] : content;

    if (
        template &&
        (template.startTag.attributes as TemplateNode[]).some((attribute) => api.ast.getDirectiveName(attribute) !== 'slot')
    ) {
        return { manualReason: `The "${source.slot}" slot is rendered conditionally.` };
    }

    if (content.length === 0) {
        return { source, slotNodes, value: null };
    }

    if (TWIG.test(getContentSource(api, template, content))) {
        return { manualReason: `The "${source.slot}" slot contains Twig, which a prop cannot hold.` };
    }

    const value = source.childElement ? readSlotElement(api, source, content) : api.ast.getContentValue(content);

    if (!value || value.kind === 'none') {
        return { manualReason: `The "${source.slot}" slot renders content that the prop cannot hold.` };
    }

    const droppedAttributes = source.childElement ? findOtherAttributes(api, content[0], source.attribute) : [];

    return { source, slotNodes, value, ...(droppedAttributes.length > 0 ? { droppedAttributes } : {}) };
}

/**
 * Returns the source around the slot content: the inside of its template, or for content without a
 * template the source between its neighbours, where Twig tags around the content sit.
 */
function getContentSource(api: ComponentUsageRuleApi, template: TemplateNode | undefined, content: TemplateNode[]): string {
    const parent = template ?? api.node;
    const siblings = parent.children as TemplateNode[];
    const before = template ? undefined : siblings[siblings.indexOf(content[0]) - 1];
    const after = template ? undefined : siblings[siblings.indexOf(content[content.length - 1]) + 1];
    const start = (before?.range[1] ?? parent.startTag.range[1]) as number;
    const end = (after?.range[0] ?? parent.endTag?.range[0] ?? parent.range[1]) as number;

    return api.sourceCode.text.slice(start, end);
}

function isSlotTemplate(api: ComponentUsageRuleApi, node: TemplateNode): boolean {
    return (
        node.type === 'VElement' &&
        node.name === 'template' &&
        (node.startTag.attributes as TemplateNode[]).some((attribute) => api.ast.getDirectiveName(attribute) === 'slot')
    );
}

/**
 * Reads the attribute of the single element a slot holds, for example the name of the icon in an
 * icon slot.
 */
function readSlotElement(
    api: ComponentUsageRuleApi,
    source: Extract<PropSource, { from: 'slot' }>,
    content: TemplateNode[],
): Value | null {
    const [element] = content;
    const isListedElement =
        content.length === 1 && element.type === 'VElement' && source.childElement?.includes(element.name as string);
    const attribute =
        isListedElement && source.attribute ? api.ast.findMatchingPropAttribute(element, source.attribute) : undefined;

    return attribute ? readAttribute(api, attribute) : null;
}

/**
 * Names the attributes of a slot element besides the one the prop takes, such as the size of an icon.
 *
 * @example
 * findOtherAttributes(api, iconOf('<mt-icon name="x" size="40px" />'), 'name'); // ['size']
 */
function findOtherAttributes(api: ComponentUsageRuleApi, element: TemplateNode, attributeName?: string): string[] {
    const read = attributeName ? api.ast.findMatchingPropAttribute(element, attributeName) : undefined;

    return (element.startTag.attributes as TemplateNode[])
        .filter((attribute) => attribute !== read)
        .map((attribute) => api.sourceCode.getText(attribute.key));
}

/**
 * Tells whether the element fills a slot whose name is only known at runtime, like `<template #[name]>`.
 */
function hasDynamicSlot(api: ComponentUsageRuleApi): boolean {
    return (api.node.children as TemplateNode[]).some((child) => {
        return (
            child.type === 'VElement' &&
            child.name === 'template' &&
            (child.startTag.attributes as TemplateNode[]).some((attribute) => {
                return (
                    api.ast.getDirectiveName(attribute) === 'slot' && attribute.key.argument?.type === 'VExpressionContainer'
                );
            })
        );
    });
}

/** The changes that turn the found sources into the new prop. */
type Edit = {
    remove: TemplateNode[];
    /** The prop source that only gets the new name, keeping its place and value. */
    rename?: TemplateNode;
    /** The attribute text of the new prop, when it is added. */
    insert?: string;
};

/**
 * Plans the new prop from the found sources, or returns null when the element already passes it as
 * the new component expects.
 */
function planProp(api: ComponentUsageRuleApi, step: PropFromSourceStep, sources: FoundSource[]): UsagePlan | null {
    const chosen = sources.find((found) => found.value !== null);
    const toggleAttribute = step.oldComponentToggle
        ? api.ast.findMatchingPropAttribute(api.node, step.oldComponentToggle.prop)
        : undefined;
    const toggle = toggleAttribute ? readToggle(api, toggleAttribute) : true;

    if (sources.length === 0 && !toggleAttribute && !step.oldComponentFallback) {
        return null;
    }

    const value = applyToggle(step, chosen?.value ?? fallbackValue(step), toggle);
    const attribute = value ? api.ast.toPropAttribute(step.toProp, value) : null;

    if (value && !attribute) {
        return { manualReason: `The value mixes single and double quotes. Pass it as the "${step.toProp}" prop manually.` };
    }

    const edit = planEdit(api, step, { sources, chosen, toggleAttribute, value, attribute });
    const reviews = collectReviews(api, step, { chosen, value, toggle });
    const reviewReason = reviews.length > 0 ? { reviewReason: reviews.join(' ') } : null;

    if (edit.remove.length === 0 && !edit.rename && !edit.insert) {
        return reviewReason;
    }

    return { fix: (fixer: Fixer) => applyEdit(api, fixer, step, edit), ...reviewReason };
}

function fallbackValue(step: PropFromSourceStep): Value | null {
    return step.oldComponentFallback ? { kind: 'expr', code: step.oldComponentFallback } : null;
}

/**
 * Folds the toggle prop into the value: `valueWhenOff` while it is false, a conditional expression
 * while it is bound to an expression.
 *
 * @example
 * applyToggle(step, { kind: 'static', text: 'Hi' }, 'isShown'); // { kind: 'expr', code: "isShown ? 'Hi' : ''" }
 */
function applyToggle(step: PropFromSourceStep, value: Value | null, toggle: boolean | string): Value | null {
    if (!step.oldComponentToggle || toggle === true) {
        return value;
    }

    const valueWhenOff: Value = { kind: 'static', text: step.oldComponentToggle.valueWhenOff };

    if (toggle === false) {
        return valueWhenOff;
    }

    return value ? { kind: 'expr', code: `${wrap(toggle)} ? ${toCode(value)} : ${toCode(valueWhenOff)}` } : value;
}

function planEdit(
    api: ComponentUsageRuleApi,
    step: PropFromSourceStep,
    plan: {
        sources: FoundSource[];
        chosen: FoundSource | undefined;
        toggleAttribute: TemplateNode | undefined;
        value: Value | null;
        attribute: string | null;
    },
): Edit {
    const { sources, chosen, toggleAttribute, attribute } = plan;
    const { keeps, rename } = findReusedAttribute(api, step, chosen, plan.value);
    const removedAttributes = sources.flatMap((found) => {
        return found.attribute && found.attribute !== keeps && found.attribute !== rename ? [found.attribute] : [];
    });

    if (keeps && sources.length === 1 && !toggleAttribute) {
        return { remove: [] };
    }

    return {
        remove: [
            ...removedAttributes,
            ...(toggleAttribute ? [toggleAttribute] : []),
            ...sources.flatMap((found) => found.slotNodes ?? []),
        ],
        rename,
        insert: attribute && !keeps && !rename ? attribute : undefined,
    };
}

/**
 * Finds the chosen prop attribute when its value goes unchanged into the new prop: it stays as it is
 * when it already is the new prop (`keeps`), otherwise it only gets the new name (`rename`).
 */
function findReusedAttribute(
    api: ComponentUsageRuleApi,
    step: PropFromSourceStep,
    chosen: FoundSource | undefined,
    value: Value | null,
): { keeps?: TemplateNode; rename?: TemplateNode } {
    if (!chosen?.attribute || value !== chosen.value) {
        return {};
    }

    return isTargetAttribute(api, step, chosen.attribute) ? { keeps: chosen.attribute } : { rename: chosen.attribute };
}

function collectReviews(
    api: ComponentUsageRuleApi,
    step: PropFromSourceStep,
    plan: { chosen: FoundSource | undefined; value: Value | null; toggle: boolean | string },
): string[] {
    const reviews: string[] = [];
    // The element's own target prop is codemod output from an earlier pass, not a source of the old component
    const chosen = plan.chosen && listSources(step).includes(plan.chosen.source) ? plan.chosen : undefined;

    if (plan.toggle !== false && losesFallback(api, step, chosen)) {
        reviews.push(
            `"${step.toProp}" no longer falls back to "${step.oldComponentFallback}" when it is null or undefined.`,
        );
    }

    if (step.plainText && plan.value?.kind === 'static' && /<[a-z]/i.test(plan.value.text)) {
        reviews.push(`"${step.toProp}" is rendered as plain text, so HTML in it shows as text.`);
    }

    if (plan.toggle !== false && step.plainText && isBoundProp(api, step, chosen)) {
        reviews.push(`"${step.toProp}" is rendered as plain text: check that the bound value holds no HTML.`);
    }

    if (chosen?.droppedAttributes) {
        const names = chosen.droppedAttributes.map((name) => `"${name}"`).join(', ');

        reviews.push(`The slot element's ${names} cannot move into "${step.toProp}" and is dropped.`);
    }

    return reviews;
}

function applyEdit(api: ComponentUsageRuleApi, fixer: Fixer, step: PropFromSourceStep, edit: Edit): Fix[] {
    const fixes = edit.remove.map((node) => {
        return node.type === 'VAttribute'
            ? api.removeAttribute(fixer, node)
            : fixer.removeRange(api.ast.getRemovalRange(node));
    });
    const removedContent = edit.remove.filter((node) => node.type !== 'VAttribute');

    fixes.push(
        ...api.keepCommentsOf(
            fixer,
            removedContent.map((node) => node.range as [number, number]),
        ),
    );

    if (edit.rename) {
        const key = (edit.rename.directive ? edit.rename.key.argument : edit.rename.key) as TemplateNode;
        fixes.push(fixer.replaceText(key, step.toProp));
    }

    if (edit.insert) {
        fixes.push(insertAttributeAtEnd(api, fixer, edit.insert));
    }

    return fixes;
}

function isTargetAttribute(api: ComponentUsageRuleApi, step: PropFromSourceStep, attribute: TemplateNode): boolean {
    return api.ast.findMatchingPropAttribute(api.node, step.toProp) === attribute;
}

/**
 * Reads the toggle prop: true when it is set or bound to true, false when bound to false, otherwise
 * the bound expression.
 */
function readToggle(api: ComponentUsageRuleApi, attribute: TemplateNode): boolean | string {
    if (api.ast.getDirectiveName(attribute) !== 'bind') {
        return true;
    }

    const code = api.ast.getAttributeValueSource(attribute);

    if (!code || code === 'true') {
        return true;
    }

    return code === 'false' ? false : code;
}

/** Tells whether the chosen source is a bound prop other than the old component's fallback itself. */
function isBoundProp(api: ComponentUsageRuleApi, step: PropFromSourceStep, chosen: FoundSource | undefined): boolean {
    const attribute = chosen?.attribute;

    if (!attribute || api.ast.getDirectiveName(attribute) !== 'bind') {
        return false;
    }

    const code = api.ast.getAttributeValueSource(attribute) ?? '';

    return code.replace(/\s/g, '') !== (step.oldComponentFallback ?? '').replace(/\s/g, '');
}

/**
 * Tells whether the chosen source is a bound prop that can be null or undefined, in which case the old
 * component used the fallback.
 */
function losesFallback(api: ComponentUsageRuleApi, step: PropFromSourceStep, chosen: FoundSource | undefined): boolean {
    const attribute = chosen?.attribute;

    if (!step.oldComponentFallback || !attribute || api.ast.getDirectiveName(attribute) !== 'bind') {
        return false;
    }

    const code = api.ast.getAttributeValueSource(attribute) ?? '';

    return (
        !isNeverNullish(attribute.value?.expression) &&
        code.replace(/\s/g, '') !== step.oldComponentFallback.replace(/\s/g, '')
    );
}

/**
 * Tells whether an expression always has a value: literals except null, template literals, binary
 * expressions, translation calls, and conditionals or logical fallbacks of those.
 *
 * @example
 * isNeverNullish(parse("filtered ? $t('a') : 'b'")); // true
 */
function isNeverNullish(expression: TemplateNode | undefined): boolean {
    switch (expression?.type) {
        case 'Literal':
            return expression.value !== null;
        case 'TemplateLiteral':
        case 'BinaryExpression':
            return true;
        case 'CallExpression':
            return expression.callee.type === 'Identifier' && STRING_FUNCTIONS.includes(expression.callee.name as string);
        case 'ConditionalExpression':
            return isNeverNullish(expression.consequent) && isNeverNullish(expression.alternate);
        case 'LogicalExpression':
            return expression.operator !== '&&' && isNeverNullish(expression.right);
        default:
            return false;
    }
}

function wrap(code: string): string {
    return /^[\w$.]+$/.test(code) ? code : `(${code})`;
}

function toCode(value: Value): string {
    return value.kind === 'static' ? `'${value.text.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'` : value.code;
}
