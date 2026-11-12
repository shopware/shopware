/**
 * @sw-package framework
 *
 * Builds an upgrade codemod that only writes TODO comments, from a data table:
 *
 * ```js
 * module.exports = createTodoRule({
 *     id: 'order-unused-components',                      // registry id, part of the TODO tag
 *     description: 'Removed order components',
 *     components: { 'sw-order-user-card': 'sw-order-user-card is removed without replacement.' },
 *     members: [{ component: 'sw-order-detail', name: 'isLoading', message: '…' }],
 *     calls: [{ service: 'orderService', method: 'toggleAutomaticPromotions', message: '…' }],
 *     globals: [{ path: 'Shopware.State', message: '…' }],
 *     literals: [{ value: '/_info/queue.json', message: '…' }],
 *     imports: [{ source: /^vuex$/, message: '…' }],
 *     events: [{ component: 'sw-media-grid', name: 'media-grid-selection-clear', message: '…' }],
 *     visitors: (context, api) => ({ CallExpression(node) { … api.todo(node, '…') } }),
 * });
 * ```
 *
 * - `components` match template tags and `Component.override/extend/build` calls with that name or base.
 * - `members` match declared option keys and `this.<name>` inside an override or extension of `component`
 *   (a string or a list), and free identifiers in templates that override it, `{{ … }}` of `.html.twig` included
 *   (see twig-interpolations.js). With `unique: true` the name
 *   matches anywhere. `kinds` limits the match to option kinds such as `['methods']`.
 * - `calls` match `this.<service>.<method>()`, `Shopware.Service('<service>').<method>()` and `<service>.<method>()`.
 * - `globals` match member chains that start with `path`, also through `const { X } = Shopware`.
 * - `literals` match string literals, `imports` match import sources (string or RegExp).
 * - `events` match `@<name>` listeners on the component's tag and `$emit('<name>')` in its overrides.
 * - `visitors` adds script visitors; `templateVisitors` adds template visitors. Both get `api.todo(node, message, scope)`.
 */

const { reportTodo, isTwigFile } = require('./todo-comment');
const { readComponentCall, elementName, stringValue } = require('./component-tags');
const { findOverrides, targetAt } = require('./override-target');
const { declaredMembers, thisMemberName, destructuredThisMembers, templateMemberReferences } = require('./member-usage');
const { shopwareAliases, memberPath, serviceCall } = require('./global-calls');
const { targetsOfTemplate } = require('./twig-target');
const { twigInterpolations } = require('./twig-interpolations');

const asList = (value) => (Array.isArray(value) ? value : [value]);
const CORE_COMPONENT_NAME = /^sw-[a-z0-9-]+$/;

function matches(pattern, value) {
    return pattern instanceof RegExp ? pattern.test(value) : pattern === value;
}

/**
 * Where a template TODO goes: above the element for an expression in an attribute (a comment may not sit inside
 * a start tag), above the expression for interpolations in text.
 *
 * @param {object} node a template node
 * @returns {object}
 */
function templateAnchor(node) {
    for (let current = node; current; current = current.parent) {
        if (current.type === 'VStartTag') {
            return current.parent;
        }

        if (current.type === 'VExpressionContainer' && current.parent?.type === 'VElement') {
            return current;
        }
    }

    return node;
}

function mergeVisitors(...visitorSets) {
    const merged = {};

    for (const visitors of visitorSets) {
        for (const [key, handler] of Object.entries(visitors ?? {})) {
            const previous = merged[key];

            merged[key] = previous
                ? (...args) => {
                      previous(...args);
                      handler(...args);
                  }
                : handler;
        }
    }

    return merged;
}

/**
 * @param {object} spec see the file comment
 * @returns {import('eslint').Rule.RuleModule}
 */
function createTodoRule(spec) {
    const components = new Map(Object.entries(spec.components ?? {}));
    const members = spec.members ?? [];
    const calls = spec.calls ?? [];
    const globals = spec.globals ?? [];
    const literals = spec.literals ?? [];
    const imports = spec.imports ?? [];
    const events = spec.events ?? [];

    /**
     * A unique name matches anywhere, except in an override of another core component that declares it itself:
     * there it belongs to that component.
     */
    function memberEntry(targets, name, kind, declared = false) {
        return members.find((entry) => {
            if (entry.name !== name || (kind && entry.kinds && !entry.kinds.includes(kind))) {
                return false;
            }

            const owners = entry.component ? asList(entry.component) : [];

            if (targets.some((target) => owners.includes(target))) {
                return true;
            }

            if (!entry.unique) {
                return false;
            }

            return !declared || owners.length === 0 || !targets.some((target) => CORE_COMPONENT_NAME.test(target));
        });
    }

    return {
        meta: {
            type: 'problem',
            fixable: 'code',
            docs: { description: spec.description ?? `Marks spots of the ${spec.id} upgrade with a TODO comment.` },
            schema: [],
        },
        create(context) {
            const seen = new Set();
            const todo = (node, message, scope = 'script') =>
                reportTodo(context, {
                    id: spec.id,
                    message,
                    range: (scope === 'template' ? templateAnchor(node) : node).range,
                    scope,
                    seen,
                });
            let aliases = new Map();
            const api = {
                todo,
                aliases: () => aliases,
                memberPath: (node) => memberPath(node, aliases),
                serviceCall: (node) => serviceCall(node, aliases),
                targetAt: (node) => targetAt(context, node),
            };

            const scriptVisitors = {
                Program(node) {
                    aliases = shopwareAliases(node);

                    if (members.length === 0) {
                        return;
                    }

                    for (const override of findOverrides(context)) {
                        if (!override.options) {
                            continue;
                        }

                        for (const member of declaredMembers(override.options)) {
                            const entry = memberEntry([override.target], member.name, member.kind, true);

                            if (entry) {
                                todo(member.node, entry.message);
                            }
                        }
                    }
                },
                CallExpression(node) {
                    const call = readComponentCall(node);

                    if (call) {
                        const name = call.method === 'extend' ? call.target : call.name;

                        if (name && components.has(name)) {
                            todo(node, components.get(name));
                        }
                    }

                    if (calls.length > 0) {
                        const found = serviceCall(node, aliases);
                        const entry = found && calls.find((candidate) => candidate.service === found.service && candidate.method === found.method);

                        if (entry) {
                            todo(node, entry.message);
                        }
                    }

                    if (events.length > 0 && node.callee.type === 'MemberExpression' && thisMemberName(node.callee) === '$emit') {
                        const eventName = stringValue(node.arguments[0]);
                        const target = targetAt(context, node);
                        const entry = events.find(
                            (candidate) => candidate.name === eventName && (!candidate.component || asList(candidate.component).includes(target)),
                        );

                        if (entry) {
                            todo(node, entry.message);
                        }
                    }
                },
                MemberExpression(node) {
                    const name = thisMemberName(node);

                    if (name && members.length > 0) {
                        const target = targetAt(context, node);
                        const entry = memberEntry(target ? [target] : [], name);

                        if (entry) {
                            todo(node, entry.message);
                        }
                    }

                    if (globals.length > 0 && !(node.parent.type === 'MemberExpression' && node.parent.object === node)) {
                        const resolved = memberPath(node, aliases);
                        const entry = resolved && globals.find((candidate) => resolved === candidate.path || resolved.startsWith(`${candidate.path}.`));

                        if (entry) {
                            todo(node, entry.message);
                        }
                    }
                },
                VariableDeclarator(node) {
                    if (members.length === 0) {
                        return;
                    }

                    for (const member of destructuredThisMembers(node)) {
                        const target = targetAt(context, node);
                        const entry = memberEntry(target ? [target] : [], member.name);

                        if (entry) {
                            todo(member.node, entry.message);
                        }
                    }
                },
                Literal(node) {
                    if (typeof node.value !== 'string' || literals.length === 0) {
                        return;
                    }

                    const entry = literals.find((candidate) => matches(candidate.value, node.value));

                    if (entry && node.parent.type !== 'ImportDeclaration') {
                        todo(node, entry.message);
                    }
                },
                ImportDeclaration(node) {
                    const entry = imports.find((candidate) => matches(candidate.source, node.source.value));

                    if (entry) {
                        todo(node, entry.message);
                    }
                },
            };

            const parserServices = context.sourceCode.parserServices;

            if (!parserServices?.defineTemplateBodyVisitor) {
                return mergeVisitors(scriptVisitors, spec.visitors?.(context, api));
            }

            let templateTargets = null;
            const targetsOfFile = () => {
                templateTargets ??= targetsOfTemplate(context);

                return templateTargets;
            };

            const templateVisitors = {
                VElement(node) {
                    const message = components.get(elementName(node));

                    if (message) {
                        todo(node, message, 'template');
                    }

                    if (events.length === 0) {
                        return;
                    }

                    for (const attribute of node.startTag.attributes) {
                        if (!attribute.directive || attribute.key.name?.name !== 'on' || attribute.key.argument?.type !== 'VIdentifier') {
                            continue;
                        }

                        const eventName = attribute.key.argument.rawName ?? attribute.key.argument.name;
                        const entry = events.find(
                            (candidate) => candidate.name === eventName && (!candidate.component || asList(candidate.component).includes(elementName(node))),
                        );

                        if (entry) {
                            todo(node, entry.message, 'template');
                        }
                    }
                },
                VExpressionContainer(node) {
                    if (members.length === 0) {
                        return;
                    }

                    for (const identifier of templateMemberReferences(node)) {
                        const entry = memberEntry(targetsOfFile(), identifier.name);

                        if (entry) {
                            todo(identifier, entry.message, 'template');
                        }
                    }
                },
                // In `.html.twig` files the parser keeps `{{ … }}` as text, so interpolations are read separately.
                VText(node) {
                    if (members.length === 0 || !isTwigFile(context)) {
                        return;
                    }

                    for (const { references } of twigInterpolations(context.sourceCode, node)) {
                        for (const identifier of references) {
                            const entry = memberEntry(targetsOfFile(), identifier.name);

                            if (entry) {
                                todo(identifier, entry.message, 'template');
                            }
                        }
                    }
                },
            };

            return parserServices.defineTemplateBodyVisitor(
                mergeVisitors(templateVisitors, spec.templateVisitors?.(context, api)),
                mergeVisitors(scriptVisitors, spec.visitors?.(context, api)),
            );
        },
    };
}

module.exports = {
    createTodoRule,
    templateAnchor,
};
