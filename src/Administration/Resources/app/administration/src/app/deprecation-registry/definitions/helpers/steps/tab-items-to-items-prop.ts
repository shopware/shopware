/* eslint-disable @typescript-eslint/no-unsafe-member-access, @typescript-eslint/no-unsafe-argument, @typescript-eslint/no-unsafe-assignment, @typescript-eslint/no-unsafe-return, @typescript-eslint/no-unsafe-call -- vue-eslint-parser template nodes are untyped records */
/**
 * @sw-package framework
 */

import type { ComponentUsageRuleApi, StepConfig, TabItemsToItemsPropStep, TemplateNode } from '../types';
import { attempt, defineUsageStep, fail, reportUsage } from '../shared';

const INDENT = '    ';
const TWIG = /{%|%}|{#|#}/;

const MANUAL =
    'Pass the sw-tabs-item children as :items ({ name, label, hasError, disabled, badge, onClick }); ' +
    'add small, and @new-item-active now receives the item name.';

type AttributeInfo =
    | { kind: 'static' | 'bound'; name: string }
    | { kind: 'directive'; directive: string; argument: string | null }
    | { kind: 'dynamic' };

type Plan = {
    attributes: string[];
    events: string[];
    hasSmall: boolean;
    defaultItemFromRoute: boolean;
    items: string[];
    content: TemplateNode[];
};

/**
 * Turns the `sw-tabs-item` children of `sw-tabs` into the `items` of `mt-tabs`, in one rewrite of the element.
 *
 * - Each item becomes `{ name, label, hasError, disabled, badge, onClick }`. The label keeps `$t(…)` as an
 *   expression, `has-warning` becomes `badge: 'warning'` and a `route` object with a static `name` becomes the item
 *   name plus `onClick: () => $router.push(…)`, with `:default-item="$route.name"` on the tabs.
 * - `v-if` and `v-show` add the item conditionally (`...(condition ? [item] : [])`). The `hidden` flag of a tab item
 *   is ignored by the Meteor version that 6.7 ships, and the condition also guards the item's other expressions.
 * - `small` is added, because `sw-tabs` was small by default and `mt-tabs` is not.
 * - `$event.name` in `@new-item-active` becomes `$event`, because `mt-tabs` emits the item name instead of the item.
 * - `is-vertical` becomes `vertical`, `align-right` is removed, the `#content` slot is kept.
 * - all or nothing: when one part cannot be converted, the element is not converted and gets the reason
 *
 * @example
 * onConvert: [renameComponent({ from: 'sw-tabs', to: 'mt-tabs' }), tabItemsToItemsProp()]
 * // <sw-tabs><sw-tabs-item name="a">A</sw-tabs-item></sw-tabs> → <mt-tabs small :items="[{ name: 'a', label: 'A' }]" />
 */
export function tabItemsToItemsProp({
    fix = 'auto',
    ...config
}: StepConfig<TabItemsToItemsPropStep> = {}): TabItemsToItemsPropStep {
    return defineUsageStep<TabItemsToItemsPropStep>({ kind: 'tab-items-to-items-prop', fix, ...config }, (api) => {
        // A converted element passes its items already
        if (api.node.name !== api.migration.component && api.ast.hasMatchingPropAttribute(api.node, 'items')) {
            return;
        }

        const { result: plan, reason } = attempt(() => analyze(api, api.node));

        if (reason !== null) {
            reportUsage(api, {
                node: api.node,
                apiName: 'sw-tabs-item',
                headline: `${MANUAL} Not converted automatically because of ${reason}.`,
                blocksConversion: true,
            });

            return;
        }

        const nameEnd = (api.node.startTag.range[0] as number) + 1 + (api.node.rawName as string).length;
        const element = buildMtTabs(api, api.node, plan);

        reportUsage(api, {
            node: api.node.startTag,
            apiName: 'sw-tabs-item',
            headline: 'The sw-tabs-item children move into the items prop.',
            fix: (fixer) => fixer.replaceTextRange([nameEnd, api.node.range[1]], element.slice('<mt-tabs'.length)),
        });
    });
}

function normalizedName(name: string): string {
    return name.replace(/-/g, '').toLowerCase();
}

function elementName(element: TemplateNode): string {
    return String(element.rawName ?? element.name)
        .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
        .toLowerCase();
}

function quote(text: string): string {
    return `'${text.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}

function readAttribute(attribute: TemplateNode): AttributeInfo {
    if (!attribute.directive) {
        return { kind: 'static', name: normalizedName(attribute.key.rawName ?? attribute.key.name) };
    }

    const directive = attribute.key.name.name as string;
    const argument = attribute.key.argument;

    if (argument && argument.type !== 'VIdentifier') {
        return { kind: 'dynamic' };
    }

    const argumentName = argument ? normalizedName(argument.rawName ?? argument.name) : null;

    if (directive === 'bind') {
        return argumentName ? { kind: 'bound', name: argumentName } : { kind: 'dynamic' };
    }

    return { kind: 'directive', directive, argument: argumentName };
}

/**
 * Wraps an expression in parentheses unless it is a plain (negated) identifier or member chain.
 */
function wrap(code: string): string {
    return /^!?[\w$.]+$/.test(code) ? code : `(${code})`;
}

function expressionCode(api: ComponentUsageRuleApi, attribute: TemplateNode, reason: string): string {
    const expression = attribute.value?.expression;
    const code = expression ? api.ast.toAttributeExpression(api.sourceCode.getText(expression)) : null;

    return code ?? fail(reason);
}

/**
 * A static or bound boolean prop as JS source. A static attribute is always `true` for a Boolean prop.
 */
function booleanCode(api: ComponentUsageRuleApi, attribute: TemplateNode, info: AttributeInfo): string {
    return info.kind === 'static' ? 'true' : expressionCode(api, attribute, 'a value with mixed quotes on sw-tabs-item');
}

/**
 * Replaces the key of a static or bound attribute and keeps the rest of its source text.
 */
function renameAttribute(api: ComponentUsageRuleApi, attribute: TemplateNode, newName: string): string {
    const keyNode = attribute.directive ? attribute.key.argument : attribute.key;
    const text = api.sourceCode.getText(attribute);
    const offset = attribute.range[0] as number;

    return `${text.slice(0, keyNode.range[0] - offset)}${newName}${text.slice(keyNode.range[1] - offset)}`;
}

/**
 * Visits every node below `root` depth-first. Skips `parent` links and token/comment arrays.
 */
function walk(root: TemplateNode, visit: (node: TemplateNode) => void): void {
    const stack = [root];

    while (stack.length > 0) {
        const node = stack.pop();

        if (!node || typeof node.type !== 'string') {
            continue;
        }

        visit(node);

        Object.keys(node)
            .filter(
                (key) =>
                    ![
                        'parent',
                        'tokens',
                        'comments',
                        'templateBody',
                    ].includes(key),
            )
            .forEach((key) => {
                const value = node[key];

                if (Array.isArray(value)) {
                    stack.push(...[...value].reverse());
                } else if (value && typeof value.type === 'string') {
                    stack.push(value);
                }
            });
    }
}

/**
 * Every `$event` reference in an expression, without property keys.
 */
function eventReferences(root: TemplateNode): Array<{ node: TemplateNode; parent: TemplateNode }> {
    const references: Array<{ node: TemplateNode; parent: TemplateNode }> = [];

    walk(root, (node) => {
        if (node.type !== 'Identifier' || node.name !== '$event') {
            return;
        }

        const parent = node.parent;
        const isMemberKey = parent?.type === 'MemberExpression' && parent.property === node && !parent.computed;
        const isPropertyKey = parent?.type === 'Property' && parent.key === node && !parent.computed && !parent.shorthand;

        if (!isMemberKey && !isPropertyKey) {
            references.push({ node, parent });
        }
    });

    return references;
}

/**
 * Rewrites `$event.name` to `$event` in an inline `@new-item-active` handler.
 * Handlers that get the payload otherwise (method references, functions, `$event` itself) can't be converted.
 */
function newItemActiveHandler(api: ComponentUsageRuleApi, attribute: TemplateNode): string {
    const reason = 'the @new-item-active handler, which receives the item name instead of the item now';
    const expression = attribute.value?.expression;
    const text = api.sourceCode.getText(attribute);

    if (!expression) {
        return text;
    }

    if (expression.type !== 'VOnExpression') {
        fail(reason);
    }

    const references = eventReferences(expression);

    references.forEach(({ node, parent }) => {
        const isNameAccess =
            parent?.type === 'MemberExpression' &&
            parent.object === node &&
            !parent.computed &&
            parent.property.type === 'Identifier' &&
            parent.property.name === 'name';

        if (!isNameAccess) {
            fail(reason);
        }
    });

    const offset = attribute.range[0] as number;

    return references
        .map(({ parent }) => parent.range as [number, number])
        .sort((a, b) => b[0] - a[0])
        .reduce((result, [start, end]) => `${result.slice(0, start - offset)}$event${result.slice(end - offset)}`, text);
}

/**
 * The `v-slot` of a `<template>` child, or null.
 */
function slotOf(element: TemplateNode): { dynamic: true } | { name: string; directive: TemplateNode } | null {
    if (element.name !== 'template') {
        return null;
    }

    const directive = element.startTag.attributes.find((attribute: TemplateNode) => {
        return attribute.directive && attribute.key.name.name === 'slot';
    });

    if (!directive) {
        return null;
    }

    const argument = directive.key.argument;

    if (argument && argument.type !== 'VIdentifier') {
        return { dynamic: true };
    }

    return { name: argument ? (argument.rawName ?? argument.name) : 'default', directive };
}

/**
 * The expressions under which the default slot exposes `active`, e.g. `active` for `#default="{ active }"`.
 */
function activeReferences(directive: TemplateNode): string[] {
    const [param] = directive.value?.expression?.params ?? [];

    if (param?.type === 'Identifier') {
        return [`${param.name}.active`];
    }

    if (param?.type !== 'ObjectPattern') {
        return [];
    }

    return param.properties
        .filter((property: TemplateNode) => property.type === 'Property' && !property.computed)
        .filter((property: TemplateNode) => {
            return (property.key.name ?? property.key.value) === 'active' && property.value.type === 'Identifier';
        })
        .map((property: TemplateNode) => property.value.name);
}

/**
 * A static text as JS string for an attribute value, or null when it contains characters an attribute would change.
 */
function staticString(text: string): string | null {
    return /["&<]/.test(text) ? null : quote(text.replace(/\s+/g, ' ').trim());
}

function staticRouteName(route: TemplateNode): string | null {
    if (route.type !== 'ObjectExpression') {
        return null;
    }

    const nameProperty = route.properties.find((property: TemplateNode) => {
        return property.type === 'Property' && !property.computed && (property.key.name ?? property.key.value) === 'name';
    });

    const value = nameProperty?.value;

    return value?.type === 'Literal' && typeof value.value === 'string' ? value.value : null;
}

type ItemState = {
    name: string | null;
    route: TemplateNode | null;
    title: string | null;
    condition: string | null;
    flags: Record<string, string>;
    /** How the `#default` slot exposes `active`, see `activeReferences`. */
    activeRefs: string[];
};

type PropInfo = { kind: 'static' | 'bound'; name: string };

type PropReader = (api: ComponentUsageRuleApi, attribute: TemplateNode, info: PropInfo, item: ItemState) => void;

function readFlag(key: string): PropReader {
    return (api, attribute, info, item) => {
        item.flags[key] = booleanCode(api, attribute, info);
    };
}

function unsupported(reason: string): PropReader {
    return () => fail(reason);
}

/** What each prop of sw-tabs-item gives the tab item; a prop without an entry keeps the item unconverted. */
const ITEM_PROPS: Record<string, PropReader> = {
    name(_api, attribute, info, item) {
        item.name = info.kind === 'bound' ? fail('a bound :name on sw-tabs-item') : attribute.value?.value || null;
    },
    route(_api, attribute, info, item) {
        if (info.kind === 'static') {
            fail('a route path on sw-tabs-item');
        }

        item.route = attribute.value?.expression ?? fail('an empty route on sw-tabs-item');
    },
    title(api, attribute, info, item) {
        item.title =
            info.kind === 'static'
                ? (staticString(attribute.value?.value ?? '') ?? fail('a title with quotes on sw-tabs-item'))
                : expressionCode(api, attribute, 'a title with mixed quotes on sw-tabs-item');
    },
    // `:active-tab="active"` from the default slot only marks the default item, which mt-tabs does itself
    activetab(api, attribute, info, item) {
        const reference = api.sourceCode.getText(attribute.value?.expression ?? attribute).trim();

        if (info.kind !== 'bound' || !item.activeRefs.includes(reference)) {
            fail('active-tab on sw-tabs-item');
        }
    },
    class(_api, _attribute, info) {
        fail(info.kind === 'bound' ? 'a bound :class on sw-tabs-item' : 'a class on sw-tabs-item');
    },
    key() {},
    haserror: readFlag('hasError'),
    disabled: readFlag('disabled'),
    // has-warning becomes the warning badge
    haswarning: readFlag('hasWarning'),
    active: unsupported('the active prop on sw-tabs-item'),
    errortooltip: unsupported('a tooltip on sw-tabs-item'),
    warningtooltip: unsupported('a tooltip on sw-tabs-item'),
};

function readItemDirective(
    api: ComponentUsageRuleApi,
    attribute: TemplateNode,
    { directive, argument }: { directive: string; argument: string | null },
    item: ItemState,
): void {
    switch (directive) {
        case 'if':
        case 'show':
            item.condition = expressionCode(api, attribute, `a v-${directive} with mixed quotes on sw-tabs-item`);
            return;
        case 'else':
        case 'else-if':
            return fail('v-else on sw-tabs-item');
        case 'for':
            return fail('v-for on sw-tabs-item');
        case 'on':
            return fail(`@${argument ?? ''} on sw-tabs-item`);
        case 'tooltip':
            return fail('a tooltip on sw-tabs-item');
        default:
            return fail(`v-${directive} on sw-tabs-item`);
    }
}

function readItemProp(api: ComponentUsageRuleApi, attribute: TemplateNode, info: PropInfo, item: ItemState): void {
    const read =
        ITEM_PROPS[info.name] ??
        unsupported(`the ${attribute.key.rawName ?? api.sourceCode.getText(attribute.key)} attribute on sw-tabs-item`);

    read(api, attribute, info, item);
}

/**
 * The route of an item as `onClick`, and the item name it gives.
 */
function readRoute(api: ComponentUsageRuleApi, item: ItemState): { name: string | null; onClick: string | null } {
    if (!item.route) {
        return { name: item.name, onClick: null };
    }

    const routeName = staticRouteName(item.route) ?? fail('a route without a static route name on sw-tabs-item');

    if (item.name !== null && item.name !== routeName) {
        fail('a name that differs from the route name of sw-tabs-item');
    }

    const route =
        api.ast.toAttributeExpression(api.sourceCode.getText(item.route)) ??
        fail('a route with mixed quotes on sw-tabs-item');

    return { name: routeName, onClick: `() => $router.push(${route})` };
}

/**
 * Converts one `sw-tabs-item` to the JS source of a tab item.
 */
function convertItem(
    api: ComponentUsageRuleApi,
    element: TemplateNode,
    activeRefs: string[],
): { code: string; hasRoute: boolean } {
    const item: ItemState = { name: null, route: null, title: null, condition: null, flags: {}, activeRefs };

    element.startTag.attributes.forEach((attribute: TemplateNode) => {
        const info = readAttribute(attribute);

        if (info.kind === 'dynamic') {
            fail('a v-bind or v-on without a static name on sw-tabs-item');
        } else if (info.kind === 'directive') {
            readItemDirective(api, attribute, info, item);
        } else {
            readItemProp(api, attribute, info, item);
        }
    });

    const content = api.ast.readContentExpression(element) ?? fail('a label with markup in sw-tabs-item');
    const label = content.code ?? item.title ?? fail('a sw-tabs-item without a label');

    if (content.code && item.title && content.code.replace(/\s/g, '') !== item.title.replace(/\s/g, '')) {
        fail('a title that differs from the label of sw-tabs-item');
    }

    const { name, onClick } = readRoute(api, item);
    const { hasError, disabled, hasWarning } = item.flags;
    const properties = [
        `name: ${staticString(name ?? fail('a sw-tabs-item without name or route')) ?? fail('a name with quotes on sw-tabs-item')}`,
        `label: ${label}`,
        ...(hasError ? [`hasError: ${hasError}`] : []),
        ...(disabled ? [`disabled: ${disabled}`] : []),
        ...(hasWarning
            ? [`badge: ${hasWarning === 'true' ? "'warning'" : `${wrap(hasWarning)} ? 'warning' : undefined`}`]
            : []),
        ...(onClick ? [`onClick: ${onClick}`] : []),
    ];
    const code = `{ ${properties.join(', ')} }`;

    return {
        code: item.condition ? `...(${wrap(item.condition)} ? [${code}] : [])` : code,
        hasRoute: item.route !== null,
    };
}

/**
 * Reads the attributes of `sw-tabs` for `mt-tabs`, or fails with the reason it can't.
 */
function analyzeAttributes(
    api: ComponentUsageRuleApi,
    element: TemplateNode,
): Pick<Plan, 'attributes' | 'events' | 'hasSmall'> & { hasDefaultItem: boolean } {
    const plan = { attributes: [] as string[], events: [] as string[], hasSmall: false, hasDefaultItem: false };

    element.startTag.attributes.forEach((attribute: TemplateNode) => {
        const info = readAttribute(attribute);
        const text = api.sourceCode.getText(attribute);

        if (info.kind === 'dynamic') {
            fail('a v-bind or v-on without a static name on sw-tabs');
        }

        if (info.kind === 'directive') {
            if (info.directive === 'slot') {
                fail('v-slot on sw-tabs');
            }

            if (info.directive === 'on') {
                plan.events.push(info.argument === 'newitemactive' ? newItemActiveHandler(api, attribute) : text);
            } else {
                plan.attributes.push(text);
            }

            return;
        }

        if (info.kind !== 'static' && info.kind !== 'bound') {
            return;
        }

        switch (info.name) {
            case 'ref':
                return fail('a ref on sw-tabs');
            case 'usemeteorcomponent':
            case 'items':
                return fail('use-meteor-component or items on sw-tabs');
            case 'isvertical':
                plan.attributes.push(renameAttribute(api, attribute, 'vertical'));
                return;
            case 'alignright':
                return;
            default:
                plan.hasSmall ||= info.name === 'small';
                plan.hasDefaultItem ||= info.name === 'defaultitem';
                plan.attributes.push(text);
        }
    });

    return plan;
}

/**
 * Sorts the children of `sw-tabs` into the tab items and the `#content` slot, or fails with the reason it can't.
 */
function analyzeChildren(element: TemplateNode): {
    itemNodes: TemplateNode[];
    content: TemplateNode[];
    activeRefs: string[];
} {
    const content: TemplateNode[] = [];
    const defaultNodes: TemplateNode[] = [];
    let defaultTemplate: TemplateNode | null = null;
    let activeRefs: string[] = [];

    element.children.forEach((child: TemplateNode) => {
        const slot = child.type === 'VElement' ? slotOf(child) : null;

        if (!slot) {
            defaultNodes.push(child);
        } else if ('dynamic' in slot) {
            fail('a dynamic slot name on sw-tabs');
        } else if (slot.name === 'content') {
            content.push(child);
        } else if (slot.name !== 'default') {
            fail(`the #${slot.name} slot of sw-tabs`);
        } else if (child.startTag.attributes.length > 1) {
            fail('a directive on the default slot of sw-tabs');
        } else {
            defaultTemplate = child;
            activeRefs = activeReferences(slot.directive);
        }
    });

    if (defaultTemplate && defaultNodes.some((node) => node.type !== 'VText' || node.value.trim() !== '')) {
        fail('content next to the #default slot of sw-tabs');
    }

    return { itemNodes: defaultTemplate ? (defaultTemplate as TemplateNode).children : defaultNodes, content, activeRefs };
}

/**
 * Fails when Twig tags or comments sit between the tab items, which the items prop would lose.
 */
function checkForLostContent(api: ComponentUsageRuleApi, element: TemplateNode, content: TemplateNode[]): void {
    const innerStart: number = element.startTag.range[1];
    const innerEnd: number = element.endTag?.range[0] ?? innerStart;
    const isInContent = (index: number) =>
        content.some((template) => template.range[0] <= index && index < template.range[1]);
    // The #content slot is kept, so its Twig does not count
    const inner = content.reduce(
        (text: string, template) => {
            const start = (template.range[0] as number) - innerStart;
            const end = (template.range[1] as number) - innerStart;

            return `${text.slice(0, start)}${' '.repeat(end - start)}${text.slice(end)}`;
        },
        api.sourceCode.text.slice(innerStart, innerEnd),
    );

    if (TWIG.test(inner)) {
        fail('a Twig block or comment in sw-tabs');
    }

    const comments: TemplateNode[] = api.sourceCode.ast.templateBody?.comments ?? [];

    if (
        comments.some(
            (comment) => comment.range[0] >= innerStart && comment.range[1] <= innerEnd && !isInContent(comment.range[0]),
        )
    ) {
        fail('an HTML comment in sw-tabs');
    }
}

/**
 * Collects everything needed to build the `mt-tabs` element, or fails with the reason it can't.
 */
function analyze(api: ComponentUsageRuleApi, element: TemplateNode): Plan {
    const { hasDefaultItem, ...attributes } = analyzeAttributes(api, element);
    const { itemNodes, content, activeRefs } = analyzeChildren(element);

    checkForLostContent(api, element, content);

    const items = itemNodes
        .filter((node) => node.type !== 'VText' || node.value.trim() !== '')
        .map((node) => {
            if (node.type !== 'VElement') {
                fail('text next to sw-tabs-item');
            }

            if (elementName(node) !== 'sw-tabs-item') {
                fail(`a <${node.rawName ?? node.name}> element in sw-tabs`);
            }

            return convertItem(api, node, activeRefs);
        });

    const hasRoutes = items.some((item) => item.hasRoute);

    if (hasRoutes && hasDefaultItem) {
        fail('default-item on sw-tabs with route items');
    }

    return { ...attributes, defaultItemFromRoute: hasRoutes, items: items.map((item) => item.code), content };
}

function lineIndentation(text: string, index: number): string {
    const lineStart = text.lastIndexOf('\n', index - 1) + 1;

    return /^[ \t]*/.exec(text.slice(lineStart))?.[0] ?? '';
}

function contentSource(api: ComponentUsageRuleApi, template: TemplateNode, indent: string): string {
    const text = api.sourceCode.text;
    const lineStart = text.lastIndexOf('\n', template.range[0] - 1) + 1;

    if (text.slice(lineStart, template.range[0]).trim() === '') {
        return text.slice(lineStart, template.range[1]);
    }

    return `${indent}${api.sourceCode.getText(template)}`;
}

function buildMtTabs(api: ComponentUsageRuleApi, element: TemplateNode, plan: Plan): string {
    const indent = lineIndentation(api.sourceCode.text, element.range[0]);
    const attributeIndent = `${indent}${INDENT}`;
    const items = plan.items.map((item) => `${attributeIndent}${INDENT}${item},`);
    const lines = [...plan.attributes];

    if (!plan.hasSmall) {
        lines.push('small');
    }

    if (plan.defaultItemFromRoute) {
        lines.push(':default-item="$route.name"');
    }

    lines.push(items.length > 0 ? `:items="[\n${items.join('\n')}\n${attributeIndent}]"` : ':items="[]"');
    lines.push(...plan.events);

    const startTag = `<mt-tabs\n${lines.map((line) => `${attributeIndent}${line}`).join('\n')}\n${indent}`;

    if (plan.content.length === 0) {
        return `${startTag}/>`;
    }

    const content = plan.content.map((template) => contentSource(api, template, attributeIndent));

    return `${startTag}>\n${content.join('\n')}\n${indent}</mt-tabs>`;
}
