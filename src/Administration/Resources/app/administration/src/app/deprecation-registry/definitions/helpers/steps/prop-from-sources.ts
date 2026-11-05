/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-argument -- vue-eslint-parser template nodes are untyped records */
/**
 * @sw-package framework
 */

import type {
    ComponentUsageRuleApi,
    Fix,
    Fixer,
    PropFromSourcesStep,
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
};

// The parser drops Twig tags from text nodes, so they are only visible in the source
const TWIG = /{%|%}|{#|#}/;

// Calls that always return a string, so a bound value with them is never null or undefined
const STRING_FUNCTIONS = ['$t', '$tc'];

/**
 * Fills one prop of the new component from the first source the element uses, in order: a slot, an
 * element inside a slot, or a prop; otherwise from `fallback`. This is how components fell back from a
 * slot to a prop to a default. The used sources are removed. A `condition` prop of the old component
 * that hid the value is folded into the new prop.
 *
 * @example
 * propFromSources({
 *     prop: 'description',
 *     sources: [{ slot: 'default' }, { prop: 'subline' }],
 *     fallback: '$t($route.meta.$module.description)',
 *     condition: { prop: 'show-description', otherwise: '' },
 * });
 * // <sw-empty-state subline="Hi" :show-description="isShown" /> → :description="isShown ? 'Hi' : ''"
 */
export function propFromSources({ fix = 'auto', ...config }: StepConfig<PropFromSourcesStep>): PropFromSourcesStep {
    return defineUsageStep<PropFromSourcesStep>({ kind: 'prop-from-sources', fix, ...config }, (api, step) => {
        const found = findSources(api, step);

        if ('manualReason' in found) {
            reportUsage(api, {
                node: api.node,
                apiName: step.prop,
                headline: headline(step),
                manualReason: `${found.manualReason} Pass it as the "${step.prop}" prop manually.`,
            });

            return;
        }

        const plan = planProp(api, step, found.sources);

        if (plan) {
            reportUsage(api, { node: api.node, apiName: step.prop, headline: headline(step), ...plan });
        }
    });
}

function headline(step: PropFromSourcesStep): string {
    const sources = step.sources.map((source) => {
        if ('prop' in source) {
            return `the "${source.prop}" prop`;
        }

        return source.element ? `the <${source.element[0]}> in the "${source.slot}" slot` : `the "${source.slot}" slot`;
    });

    return `The "${step.prop}" prop replaces ${sources.join(' and ')}.`;
}

/**
 * Collects the sources the element uses, in their order, or why one of them cannot become a value.
 */
function findSources(
    api: ComponentUsageRuleApi,
    step: PropFromSourcesStep,
): { sources: FoundSource[] } | { manualReason: string } {
    const sources: FoundSource[] = [];
    const listsTarget = step.sources.some((source) => 'prop' in source && source.prop === step.prop);
    // An element that already passes the new prop keeps it, the old sources come after it
    const orderedSources: PropSource[] = listsTarget ? step.sources : [{ prop: step.prop }, ...step.sources];

    for (const source of orderedSources) {
        if ('prop' in source) {
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
    source: Extract<PropSource, { slot: string }>,
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

    const value = source.element ? readSlotElement(api, source, content) : api.ast.getContentValue(content);

    if (!value || value.kind === 'none') {
        return { manualReason: `The "${source.slot}" slot renders content that the prop cannot hold.` };
    }

    return { source, slotNodes, value };
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
    source: Extract<PropSource, { slot: string }>,
    content: TemplateNode[],
): Value | null {
    const [element] = content;
    const isListedElement =
        content.length === 1 && element.type === 'VElement' && source.element?.includes(element.name as string);
    const attribute =
        isListedElement && source.attribute ? api.ast.findMatchingPropAttribute(element, source.attribute) : undefined;

    return attribute ? readAttribute(api, attribute) : null;
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
function planProp(api: ComponentUsageRuleApi, step: PropFromSourcesStep, sources: FoundSource[]): UsagePlan | null {
    const chosen = sources.find((found) => found.value !== null);
    const conditionAttribute = step.condition ? api.ast.findMatchingPropAttribute(api.node, step.condition.prop) : undefined;
    const condition = conditionAttribute ? readCondition(api, conditionAttribute) : true;

    if (sources.length === 0 && !conditionAttribute && !step.fallback) {
        return null;
    }

    const value = applyCondition(step, chosen?.value ?? fallbackValue(step), condition);
    const attribute = value ? api.ast.toPropAttribute(step.prop, value) : null;

    if (value && !attribute) {
        return { manualReason: `The value mixes single and double quotes. Pass it as the "${step.prop}" prop manually.` };
    }

    const edit = planEdit(api, step, { sources, chosen, conditionAttribute, value, attribute });
    const reviews = collectReviews(api, step, { chosen, value, condition });
    const reviewReason = reviews.length > 0 ? { reviewReason: reviews.join(' ') } : null;

    if (edit.remove.length === 0 && !edit.rename && !edit.insert) {
        return reviewReason;
    }

    return { fix: (fixer: Fixer) => applyEdit(api, fixer, step, edit), ...reviewReason };
}

function fallbackValue(step: PropFromSourcesStep): Value | null {
    return step.fallback ? { kind: 'expr', code: step.fallback } : null;
}

/**
 * Folds the condition prop into the value: `otherwise` while it is false, a conditional expression
 * while it is bound to an expression.
 *
 * @example
 * applyCondition(step, { kind: 'static', text: 'Hi' }, 'isShown'); // { kind: 'expr', code: "isShown ? 'Hi' : ''" }
 */
function applyCondition(step: PropFromSourcesStep, value: Value | null, condition: boolean | string): Value | null {
    if (!step.condition || condition === true) {
        return value;
    }

    const otherwise: Value = { kind: 'static', text: step.condition.otherwise };

    if (condition === false) {
        return otherwise;
    }

    return value ? { kind: 'expr', code: `${wrap(condition)} ? ${toCode(value)} : ${toCode(otherwise)}` } : value;
}

function planEdit(
    api: ComponentUsageRuleApi,
    step: PropFromSourcesStep,
    plan: {
        sources: FoundSource[];
        chosen: FoundSource | undefined;
        conditionAttribute: TemplateNode | undefined;
        value: Value | null;
        attribute: string | null;
    },
): Edit {
    const { sources, chosen, conditionAttribute, attribute } = plan;
    const { keeps, rename } = findReusedAttribute(api, step, chosen, plan.value);
    const removedAttributes = sources.flatMap((found) => {
        return found.attribute && found.attribute !== keeps && found.attribute !== rename ? [found.attribute] : [];
    });

    if (keeps && sources.length === 1 && !conditionAttribute) {
        return { remove: [] };
    }

    return {
        remove: [
            ...removedAttributes,
            ...(conditionAttribute ? [conditionAttribute] : []),
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
    step: PropFromSourcesStep,
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
    step: PropFromSourcesStep,
    plan: { chosen: FoundSource | undefined; value: Value | null; condition: boolean | string },
): string[] {
    const reviews: string[] = [];

    if (plan.condition !== false && losesFallback(api, step, plan.chosen)) {
        reviews.push(`"${step.prop}" no longer falls back to "${step.fallback}" when it is null or undefined.`);
    }

    if (step.plainText && plan.value?.kind === 'static' && /<[a-z]/i.test(plan.value.text)) {
        reviews.push(`"${step.prop}" is rendered as plain text, so HTML in it shows as text.`);
    }

    return reviews;
}

function applyEdit(api: ComponentUsageRuleApi, fixer: Fixer, step: PropFromSourcesStep, edit: Edit): Fix[] {
    const fixes = edit.remove.map((node) => {
        return node.type === 'VAttribute'
            ? api.removeAttribute(fixer, node)
            : fixer.removeRange(api.ast.getRemovalRange(node));
    });

    if (edit.rename) {
        const key = (edit.rename.directive ? edit.rename.key.argument : edit.rename.key) as TemplateNode;
        fixes.push(fixer.replaceText(key, step.prop));
    }

    if (edit.insert) {
        fixes.push(insertAttributeAtEnd(api, fixer, edit.insert));
    }

    return fixes;
}

function isTargetAttribute(api: ComponentUsageRuleApi, step: PropFromSourcesStep, attribute: TemplateNode): boolean {
    return api.ast.findMatchingPropAttribute(api.node, step.prop) === attribute;
}

/**
 * Reads the condition prop: true when it is set or bound to true, false when bound to false, otherwise
 * the bound expression.
 */
function readCondition(api: ComponentUsageRuleApi, attribute: TemplateNode): boolean | string {
    if (api.ast.getDirectiveName(attribute) !== 'bind') {
        return true;
    }

    const code = api.ast.getAttributeValueSource(attribute);

    if (!code || code === 'true') {
        return true;
    }

    return code === 'false' ? false : code;
}

/**
 * Tells whether the chosen source is a bound prop that can be null or undefined, in which case the old
 * component used the fallback.
 */
function losesFallback(api: ComponentUsageRuleApi, step: PropFromSourcesStep, chosen: FoundSource | undefined): boolean {
    const attribute = chosen?.attribute;

    if (!step.fallback || !attribute || api.ast.getDirectiveName(attribute) !== 'bind') {
        return false;
    }

    const code = api.ast.getAttributeValueSource(attribute) ?? '';

    return !isNeverNullish(attribute.value?.expression) && code.replace(/\s/g, '') !== step.fallback.replace(/\s/g, '');
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
