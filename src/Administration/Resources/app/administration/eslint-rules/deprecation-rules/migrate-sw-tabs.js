/**
 * @sw-package framework
 *
 * Migrates `<sw-tabs>` with `<sw-tabs-item>` children to `<mt-tabs>` with an `items` array.
 *
 * - Each `sw-tabs-item` becomes `{ name, label, hasError, disabled, badge, onClick }`. The label keeps `$t(…)` as an
 *   expression, `has-warning` becomes `badge: 'warning'` and a `route` object with a static `name` becomes the item
 *   name plus `onClick: () => $router.push(…)`, with `:default-item="$route.name"` on the tabs.
 * - `v-if` and `v-show` add the item conditionally (`...(condition ? [item] : [])`). The `hidden` flag of a tab item
 *   is ignored by the Meteor version that 6.7 ships, and the condition also guards the item's other expressions.
 * - `small` is added, because `sw-tabs` was small by default and `mt-tabs` is not.
 * - `$event.name` in `@new-item-active` becomes `$event`, because `mt-tabs` emits the item name instead of the item.
 * - `is-vertical` becomes `vertical`, `align-right` is removed, the `#content` slot is kept.
 *
 * The conversion is all or nothing: when one part can't be converted, the rule only writes a TODO above `<sw-tabs>`.
 * A missing `position-identifier`, which `mt-tabs` requires, gets a TODO of its own.
 * `sw-tabs-item` outside of `sw-tabs`, e.g. in Twig block overrides or in the tab slots of `sw-meteor-page` and
 * `sw-meteor-card`, stays untouched.
 */

const { reportTodo } = require('./upgrade-helpers/todo-comment');
const { elementName, findAttribute } = require('./upgrade-helpers/component-tags');
const {
    TWIG_PATTERN,
    quote,
    toAttributeExpression,
    readContentExpression,
} = require('./no-deprecated-component-usage-checks/mt-tabs.check');

const ID = 'mt-tabs';
const INDENT = '    ';

const MESSAGES = {
    migrate: 'sw-tabs is deprecated. Use mt-tabs with the items prop instead.',
    positionIdentifier: 'mt-tabs requires a position-identifier. Add a unique one, so extensions can add tabs.',
};

/**
 * @param {string} reason
 * @returns {string}
 */
function manualMessage(reason) {
    return 'sw-tabs is deprecated. Replace it with mt-tabs and pass the sw-tabs-item children as :items '
        + '({ name, label, hasError, disabled, badge, onClick }); add small, and @new-item-active now receives the item name. '
        + `Not converted automatically because of ${reason}.`;
}

class Unconvertible extends Error {}

function fail(reason) {
    throw new Unconvertible(reason);
}

function normalize(name) {
    return name.replace(/-/g, '').toLowerCase();
}

/**
 * @param {object} attribute VAttribute
 * @returns {{ kind: 'static'|'bound', name: string }|{ kind: 'directive', directive: string, argument: string|null }|{ kind: 'dynamic' }}
 */
function readAttribute(attribute) {
    if (!attribute.directive) {
        return { kind: 'static', name: normalize(attribute.key.rawName ?? attribute.key.name) };
    }

    const directive = attribute.key.name.name;
    const argument = attribute.key.argument;

    if (argument && argument.type !== 'VIdentifier') {
        return { kind: 'dynamic' };
    }

    const argumentName = argument ? normalize(argument.rawName ?? argument.name) : null;

    if (directive === 'bind') {
        return argumentName ? { kind: 'bound', name: argumentName } : { kind: 'dynamic' };
    }

    return { kind: 'directive', directive, argument: argumentName };
}

function lineIndent(text, index) {
    const lineStart = text.lastIndexOf('\n', index - 1) + 1;

    return /^[ \t]*/.exec(text.slice(lineStart))[0];
}

/**
 * Wraps an expression in parentheses unless it is a plain (negated) identifier or member chain.
 */
function wrap(code) {
    return /^!?[\w$.]+$/.test(code) ? code : `(${code})`;
}

function expressionCode(sourceCode, attribute, reason) {
    const expression = attribute.value?.expression;
    const code = expression ? toAttributeExpression(sourceCode.getText(expression)) : null;

    return code ?? fail(reason);
}

/**
 * A static or bound boolean prop as JS source. A static attribute is always `true` for a Boolean prop.
 */
function booleanCode(sourceCode, attribute, info) {
    return info.kind === 'static' ? 'true' : expressionCode(sourceCode, attribute, 'a value with mixed quotes on sw-tabs-item');
}

/**
 * Replaces the key of a static or bound attribute and keeps the rest of its source text.
 */
function renameAttribute(sourceCode, attribute, newName) {
    const keyNode = attribute.directive ? attribute.key.argument : attribute.key;
    const text = sourceCode.getText(attribute);

    return `${text.slice(0, keyNode.range[0] - attribute.range[0])}${newName}${text.slice(keyNode.range[1] - attribute.range[0])}`;
}

/**
 * Every `$event` reference in an expression, without property keys.
 */
function eventReferences(root) {
    const references = [];

    const visit = (node, parent) => {
        if (!node || typeof node.type !== 'string') {
            return;
        }

        if (node.type === 'Identifier' && node.name === '$event') {
            const isMemberKey = parent?.type === 'MemberExpression' && parent.property === node && !parent.computed;
            const isPropertyKey = parent?.type === 'Property' && parent.key === node && !parent.computed && !parent.shorthand;

            if (!isMemberKey && !isPropertyKey) {
                references.push({ node, parent });
            }

            return;
        }

        Object.keys(node).forEach((key) => {
            if (['parent', 'loc', 'range', 'tokens', 'comments'].includes(key)) {
                return;
            }

            const value = node[key];

            if (Array.isArray(value)) {
                value.forEach((child) => visit(child, node));
            } else if (value && typeof value.type === 'string') {
                visit(value, node);
            }
        });
    };

    visit(root, null);

    return references;
}

/**
 * Rewrites `$event.name` to `$event` in an inline `@new-item-active` handler.
 * Handlers that get the payload otherwise (method references, functions, `$event` itself) can't be converted.
 */
function newItemActiveHandler(sourceCode, attribute) {
    const reason = 'the @new-item-active handler, which receives the item name instead of the item now';
    const expression = attribute.value?.expression;
    const text = sourceCode.getText(attribute);

    if (!expression) {
        return text;
    }

    if (expression.type !== 'VOnExpression') {
        fail(reason);
    }

    const references = eventReferences(expression);

    references.forEach(({ node, parent }) => {
        const isNameAccess = parent?.type === 'MemberExpression'
            && parent.object === node
            && !parent.computed
            && parent.property.type === 'Identifier'
            && parent.property.name === 'name';

        if (!isNameAccess) {
            fail(reason);
        }
    });

    return references
        .map(({ parent }) => parent.range)
        .sort((a, b) => b[0] - a[0])
        .reduce((result, [start, end]) => {
            const offset = attribute.range[0];

            return `${result.slice(0, start - offset)}$event${result.slice(end - offset)}`;
        }, text);
}

/**
 * The `v-slot` of a `<template>` child, or null.
 */
function slotOf(element) {
    if (element.name !== 'template') {
        return null;
    }

    const directive = element.startTag.attributes.find((attribute) => attribute.directive && attribute.key.name.name === 'slot');

    if (!directive) {
        return null;
    }

    const argument = directive.key.argument;

    if (argument && argument.type !== 'VIdentifier') {
        return { dynamic: true };
    }

    return { name: argument ? argument.rawName ?? argument.name : 'default', directive };
}

/**
 * The expressions under which the default slot exposes `active`, e.g. `active` for `#default="{ active }"`.
 */
function activeReferences(directive) {
    const [param] = directive.value?.expression?.params ?? [];

    if (param?.type === 'Identifier') {
        return [`${param.name}.active`];
    }

    if (param?.type !== 'ObjectPattern') {
        return [];
    }

    return param.properties
        .filter((property) => property.type === 'Property' && !property.computed)
        .filter((property) => (property.key.name ?? property.key.value) === 'active' && property.value.type === 'Identifier')
        .map((property) => property.value.name);
}

/**
 * A static text as JS string for an attribute value, or null when it contains characters an attribute would change.
 */
function staticString(text) {
    return /["&<]/.test(text) ? null : quote(text.replace(/\s+/g, ' ').trim());
}

function staticRouteName(route) {
    if (route.type !== 'ObjectExpression') {
        return null;
    }

    const nameProperty = route.properties.find((property) => {
        return property.type === 'Property'
            && !property.computed
            && (property.key.name ?? property.key.value) === 'name';
    });

    const value = nameProperty?.value;

    return value?.type === 'Literal' && typeof value.value === 'string' ? value.value : null;
}

/**
 * Converts one `sw-tabs-item` to the JS source of a tab item.
 *
 * @returns {{ code: string, hasRoute: boolean }}
 */
function convertItem(sourceCode, item, activeRefs) {
    let name = null;
    let route = null;
    let title = null;
    let condition = null;
    const flags = {};

    item.startTag.attributes.forEach((attribute) => {
        const info = readAttribute(attribute);

        if (info.kind === 'dynamic') {
            fail('a v-bind or v-on without a static name on sw-tabs-item');
        }

        if (info.kind === 'directive') {
            switch (info.directive) {
                case 'if':
                case 'show':
                    condition = expressionCode(sourceCode, attribute, `a v-${info.directive} with mixed quotes on sw-tabs-item`);
                    return;
                case 'else':
                case 'else-if':
                    return fail('v-else on sw-tabs-item');
                case 'for':
                    return fail('v-for on sw-tabs-item');
                case 'on':
                    return fail(`@${info.argument ?? ''} on sw-tabs-item`);
                case 'tooltip':
                    return fail('a tooltip on sw-tabs-item');
                default:
                    return fail(`v-${info.directive} on sw-tabs-item`);
            }
        }

        switch (info.name) {
            case 'name':
                if (info.kind === 'bound') {
                    fail('a bound :name on sw-tabs-item');
                }

                name = attribute.value?.value || null;
                return;
            case 'route':
                if (info.kind === 'static') {
                    fail('a route path on sw-tabs-item');
                }

                route = attribute.value?.expression ?? fail('an empty route on sw-tabs-item');
                return;
            case 'title':
                title = info.kind === 'static'
                    ? staticString(attribute.value?.value ?? '') ?? fail('a title with quotes on sw-tabs-item')
                    : expressionCode(sourceCode, attribute, 'a title with mixed quotes on sw-tabs-item');
                return;
            case 'haserror':
                flags.hasError = booleanCode(sourceCode, attribute, info);
                return;
            case 'disabled':
                flags.disabled = booleanCode(sourceCode, attribute, info);
                return;
            case 'haswarning':
                flags.hasWarning = booleanCode(sourceCode, attribute, info);
                return;
            case 'activetab':
                // `:active-tab="active"` from the default slot only marks the default item, which mt-tabs does itself
                if (info.kind !== 'bound' || !activeRefs.includes(sourceCode.getText(attribute.value?.expression ?? attribute).trim())) {
                    fail('active-tab on sw-tabs-item');
                }

                return;
            case 'active':
                return fail('the active prop on sw-tabs-item');
            case 'errortooltip':
            case 'warningtooltip':
                return fail('a tooltip on sw-tabs-item');
            case 'class':
                if (info.kind === 'bound') {
                    fail('a bound :class on sw-tabs-item');
                }

                return;
            case 'key':
                return;
            default:
                fail(`the ${attribute.key.rawName ?? sourceCode.getText(attribute.key)} attribute on sw-tabs-item`);
        }
    });

    const content = readContentExpression(sourceCode, item) ?? fail('a label with markup in sw-tabs-item');
    const label = content.code ?? title ?? fail('a sw-tabs-item without a label');

    if (content.code && title && content.code.replace(/\s/g, '') !== title.replace(/\s/g, '')) {
        fail('a title that differs from the label of sw-tabs-item');
    }

    let onClick = null;

    if (route) {
        const routeName = staticRouteName(route) ?? fail('a route without a static route name on sw-tabs-item');

        if (name !== null && name !== routeName) {
            fail('a name that differs from the route name of sw-tabs-item');
        }

        name = routeName;
        onClick = `() => $router.push(${toAttributeExpression(sourceCode.getText(route)) ?? fail('a route with mixed quotes on sw-tabs-item')})`;
    }

    if (name === null) {
        fail('a sw-tabs-item without name or route');
    }

    const properties = [
        `name: ${staticString(name) ?? fail('a name with quotes on sw-tabs-item')}`,
        `label: ${label}`,
    ];

    if (flags.hasError) {
        properties.push(`hasError: ${flags.hasError}`);
    }

    if (flags.disabled) {
        properties.push(`disabled: ${flags.disabled}`);
    }

    if (flags.hasWarning) {
        properties.push(`badge: ${flags.hasWarning === 'true' ? "'warning'" : `${wrap(flags.hasWarning)} ? 'warning' : undefined`}`);
    }

    if (onClick) {
        properties.push(`onClick: ${onClick}`);
    }

    const code = `{ ${properties.join(', ')} }`;

    return {
        code: condition ? `...(${wrap(condition)} ? [${code}] : [])` : code,
        hasRoute: route !== null,
    };
}

/**
 * Collects everything needed to build the `mt-tabs` element, or throws `Unconvertible`.
 */
function analyze(sourceCode, element) {
    const attributes = [];
    const events = [];
    let hasSmall = false;
    let hasDefaultItem = false;

    element.startTag.attributes.forEach((attribute) => {
        const info = readAttribute(attribute);
        const text = sourceCode.getText(attribute);

        if (info.kind === 'dynamic') {
            fail('a v-bind or v-on without a static name on sw-tabs');
        }

        if (info.kind === 'directive') {
            if (info.directive === 'slot') {
                fail('v-slot on sw-tabs');
            }

            if (info.directive === 'on') {
                events.push(info.argument === 'newitemactive' ? newItemActiveHandler(sourceCode, attribute) : text);

                return;
            }

            attributes.push(text);

            return;
        }

        switch (info.name) {
            case 'ref':
                return fail('a ref on sw-tabs');
            case 'usemeteorcomponent':
            case 'items':
                return fail('use-meteor-component or items on sw-tabs');
            case 'isvertical':
                attributes.push(renameAttribute(sourceCode, attribute, 'vertical'));
                return;
            case 'alignright':
                return;
            case 'small':
                hasSmall = true;
                break;
            case 'defaultitem':
                hasDefaultItem = true;
                break;
            default:
                break;
        }

        attributes.push(text);
    });

    const content = [];
    const defaultNodes = [];
    let defaultTemplate = null;
    let activeRefs = [];

    element.children.forEach((child) => {
        if (child.type !== 'VElement') {
            defaultNodes.push(child);

            return;
        }

        const slot = slotOf(child);

        if (!slot) {
            defaultNodes.push(child);

            return;
        }

        if (slot.dynamic) {
            fail('a dynamic slot name on sw-tabs');
        }

        if (slot.name === 'content') {
            content.push(child);

            return;
        }

        if (slot.name !== 'default') {
            fail(`the #${slot.name} slot of sw-tabs`);
        }

        if (child.startTag.attributes.length > 1) {
            fail('a directive on the default slot of sw-tabs');
        }

        defaultTemplate = child;
        activeRefs = activeReferences(slot.directive);
    });

    const itemNodes = defaultTemplate ? defaultTemplate.children : defaultNodes;

    if (defaultTemplate && defaultNodes.some((node) => node.type !== 'VText' || node.value.trim() !== '')) {
        fail('content next to the #default slot of sw-tabs');
    }

    // Twig tags and comments in the tab items would get lost
    const text = sourceCode.text;
    const innerRange = [element.startTag.range[1], element.endTag?.range[0] ?? element.startTag.range[1]];
    const isInContent = (index) => content.some((template) => template.range[0] <= index && index < template.range[1]);
    const inner = [...text.slice(...innerRange)]
        .map((character, offset) => (isInContent(innerRange[0] + offset) ? ' ' : character))
        .join('');

    if (TWIG_PATTERN.test(inner)) {
        fail('a Twig block or comment in sw-tabs');
    }

    const comments = sourceCode.ast.templateBody?.comments ?? [];

    if (comments.some((comment) => comment.range[0] >= innerRange[0] && comment.range[1] <= innerRange[1] && !isInContent(comment.range[0]))) {
        fail('an HTML comment in sw-tabs');
    }

    const items = [];

    itemNodes.forEach((node) => {
        if (node.type === 'VText' && node.value.trim() === '') {
            return;
        }

        if (node.type !== 'VElement') {
            fail('text next to sw-tabs-item');
        }

        if (elementName(node) !== 'sw-tabs-item') {
            fail(`a <${node.rawName ?? node.name}> element in sw-tabs`);
        }

        items.push(convertItem(sourceCode, node, activeRefs));
    });

    const hasRoutes = items.some((item) => item.hasRoute);

    if (hasRoutes && hasDefaultItem) {
        fail('default-item on sw-tabs with route items');
    }

    return {
        attributes,
        events,
        hasSmall,
        defaultItemFromRoute: hasRoutes,
        items: items.map((item) => item.code),
        content,
    };
}

function contentSource(sourceCode, template, indent) {
    const text = sourceCode.text;
    const lineStart = text.lastIndexOf('\n', template.range[0] - 1) + 1;

    if (text.slice(lineStart, template.range[0]).trim() === '') {
        return text.slice(lineStart, template.range[1]);
    }

    return `${indent}${sourceCode.getText(template)}`;
}

function buildMtTabs(sourceCode, element, plan) {
    const indent = lineIndent(sourceCode.text, element.range[0]);
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

    const content = plan.content.map((template) => contentSource(sourceCode, template, attributeIndent));

    return `${startTag}>\n${content.join('\n')}\n${indent}</mt-tabs>`;
}

/**
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = {
    meta: {
        type: 'problem',
        fixable: 'code',
        docs: {
            description: 'Migrate sw-tabs with sw-tabs-item children to mt-tabs with an items array',
        },
        schema: [],
    },

    /** @param {import('eslint').Rule.RuleContext} context */
    create(context) {
        const sourceCode = context.sourceCode;

        if (!sourceCode.parserServices?.defineTemplateBodyVisitor) {
            return {};
        }

        const seen = new Set();
        const todo = (node, message) => reportTodo(context, { id: ID, message, range: node.range, scope: 'template', seen });

        return sourceCode.parserServices.defineTemplateBodyVisitor({
            VElement(node) {
                const name = elementName(node);

                if (name !== 'sw-tabs' && name !== 'mt-tabs') {
                    return;
                }

                if (!findAttribute(node, 'position-identifier')) {
                    todo(node, MESSAGES.positionIdentifier);
                }

                if (name !== 'sw-tabs') {
                    return;
                }

                let plan;

                try {
                    plan = analyze(sourceCode, node);
                } catch (error) {
                    if (!(error instanceof Unconvertible)) {
                        throw error;
                    }

                    todo(node, manualMessage(error.message));

                    return;
                }

                context.report({
                    node: node.startTag,
                    message: MESSAGES.migrate,
                    fix: (fixer) => fixer.replaceTextRange(node.range, buildMtTabs(sourceCode, node, plan)),
                });
            },
        });
    },
};
