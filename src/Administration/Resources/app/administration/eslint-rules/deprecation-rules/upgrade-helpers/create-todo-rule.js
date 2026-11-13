/**
 * @sw-package framework
 *
 * Builds an upgrade codemod that only writes TODO comments, from a data table with `id` (registry id, part of the
 * TODO tag), `description` and any of these matchers, each entry with a `message`:
 *
 * - `components: { name: message }`: template tags and `Component.override/extend/build` calls with that name or base.
 * - `members: [{ component, name, unique?, kinds? }]`: declared option keys and `this.<name>` in overrides and
 *   extensions of `component` (a name or a list), and free identifiers in templates that override it, `{{ … }}` of
 *   `.html.twig` included. `unique: true` matches the name anywhere, `kinds` limits it to option kinds
 *   like `['methods']`.
 * - `calls: [{ service, method }]`: `this.<service>.<method>()`, `Shopware.Service('<service>').<method>()` and
 *   `<service>.<method>()`.
 * - `globals: [{ path }]`: member chains that start with `path`, also through `const { X } = Shopware`.
 * - `literals: [{ value }]`, `imports: [{ source }]`: string and template literals, where `${…}` reads as `*`, and
 *   import sources (string or RegExp).
 * - `events: [{ component, name }]`: `@<name>` on the component's tag and `$emit('<name>')` in its overrides.
 * - `visitors`, `templateVisitors`: extra visitors that get `api.todo(node, message, scope)`.
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

function createTodoRule(spec) {
    const components = new Map(Object.entries(spec.components ?? {}));
    const members = spec.members ?? [];
    const calls = spec.calls ?? [];
    const globals = spec.globals ?? [];
    const literals = spec.literals ?? [];
    const imports = spec.imports ?? [];
    const events = spec.events ?? [];
    const literalEntry = (text) => literals.find((candidate) => matches(candidate.value, text));

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
                    const entry = typeof node.value === 'string' && node.parent.type !== 'ImportDeclaration' && literalEntry(node.value);

                    if (entry) {
                        todo(node, entry.message);
                    }
                },
                TemplateLiteral(node) {
                    const entry = literalEntry(node.quasis.map((quasi) => quasi.value.cooked).join('*'));

                    if (entry) {
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
