/**
 * @sw-package framework
 *
 * Upgrade codemod `order-list-helpers`: 6.8 removes the delivery and transaction helpers of `sw-order-list`.
 * In overrides and extensions of `sw-order-list`, scripts and templates alike:
 *
 * - The six accessors become their 6.8 body, which works on 6.7 as well because the list loads the primary
 *   delivery and transaction there too: `getDeliveryState(item).translated` → `item.primaryOrderDelivery?.stateMachineState?.translated`.
 *   The argument must be an identifier or a plain member chain, otherwise the call gets a codemod comment.
 * - `deliveryTooltip()`, `filterLoading`, `filterSelectCriteria` and `this.$super()` calls of removed members get a
 *   codemod comment.
 * - Reads of `order.deliveries`, `order.transactions` and `order.addresses` get a codemod comment, because the list no
 *   longer loads these associations, unless the override adds the association itself. Scripts get one comment per
 *   association and method.
 *
 * An override that declares a member with the same name keeps it: its calls are left alone.
 * The data is in `no-order-list-delivery-helpers.data.js`.
 */

const { reportTodo, isTwigFile } = require('./upgrade-helpers/todo-comment');
const { findOverrides, fileTargets } = require('./upgrade-helpers/override-target');
const { walk, stringValue } = require('./upgrade-helpers/component-tags');
const { templateAnchor } = require('./upgrade-helpers/create-todo-rule');
const { declaredMembers, thisMemberName, destructuredThisMembers, templateMemberReferences } = require('./upgrade-helpers/member-usage');
const { importingScripts, templateTargets, scriptsDeclare } = require('./utils/override-templates');
const { twigInterpolations, locOf } = require('./upgrade-helpers/twig-interpolations');
const { component: COMPONENT, accessors: ACCESSORS, removed: REMOVED, superOnly: SUPER_ONLY, associations: ASSOCIATIONS } = require('./no-order-list-delivery-helpers.data');

const ID = 'order-list-helpers';
const ASSOCIATION_CALL = /(?:add|get)Association\(\s*(['"`])(\w+)/g;

const accessorMessage = (name) => `${name}() of ${COMPONENT} is removed in 6.8. Use order.${ACCESSORS[name]} instead.`;

const superMessage = (name) => {
    if (SUPER_ONLY[name]) {
        return SUPER_ONLY[name];
    }

    const replacement = ACCESSORS[name] ? ` Use order.${ACCESSORS[name]} instead.` : '';

    return `${name} of ${COMPONENT} is removed in 6.8, so this.$super('${name}') has nothing to call.${replacement}`;
};

const associationMessage = (association) => {
    return `${COMPONENT} no longer loads order.${association} in 6.8. Use ${ASSOCIATIONS[association]}, `
        + `or add the ${association} association to orderCriteria in this override.`;
};

/** An identifier or a chain like `item.order` or `this.order`, which the replacement can repeat as is. */
function isPlainReference(node) {
    if (node.type === 'Identifier') {
        return node.name !== 'undefined';
    }

    return node.type === 'MemberExpression'
        && !node.computed
        && !node.optional
        && node.property.type === 'Identifier'
        && (node.object.type === 'ThisExpression' || isPlainReference(node.object));
}

/** Whether the value of `node` is written to, where an optional chain is not allowed. */
function isWriteTarget(node, container) {
    let current = node;

    while (
        current.parent
        && ((current.parent.type === 'MemberExpression' && current.parent.object === current)
            || current.parent.type === 'ChainExpression'
            || current.parent.type === 'TSNonNullExpression')
    ) {
        current = current.parent;
    }

    const parent = current.parent;

    if (container?.parent?.type === 'VAttribute' && container.parent.directive && container.parent.key.name?.name === 'model') {
        return true;
    }

    return (parent?.type === 'AssignmentExpression' && parent.left === current)
        || parent?.type === 'UpdateExpression'
        || (parent?.type === 'UnaryExpression' && parent.operator === 'delete')
        || (['ForInStatement', 'ForOfStatement'].includes(parent?.type) && parent.left === current);
}

function enclosingFunction(node) {
    let current = node.parent;

    while (current && !['FunctionExpression', 'ArrowFunctionExpression', 'FunctionDeclaration'].includes(current.type)) {
        current = current.parent;
    }

    return current ?? null;
}

function associationName(node) {
    if (node.type !== 'MemberExpression' || node.object.type === 'ThisExpression') {
        return null;
    }

    const name = node.computed ? stringValue(node.property) : node.property.name;

    return Object.hasOwn(ASSOCIATIONS, name ?? '') ? name : null;
}

/** @type {import('eslint').Rule.RuleModule} */
module.exports = {
    meta: {
        type: 'problem',
        fixable: 'code',
        docs: {
            description: 'Replace the delivery and transaction helpers that 6.8 removes from sw-order-list',
        },
        schema: [],
    },

    /** @param {import('eslint').Rule.RuleContext} context */
    create(context) {
        const sourceCode = context.sourceCode;
        const seen = new Set();
        const todo = (node, message, scope) => {
            reportTodo(context, {
                id: ID,
                message,
                range: (scope === 'template' ? templateAnchor(node) : node).range,
                scope,
                seen,
            });
        };

        const overrides = () => findOverrides(context).filter(({ target, options }) => target === COMPONENT && options);
        const declaredCache = new Map();
        const declaredIn = (override) => {
            if (!declaredCache.has(override)) {
                declaredCache.set(override, new Set(declaredMembers(override.options).map(({ name }) => name)));
            }

            return declaredCache.get(override);
        };
        // The declared members of the sw-order-list override around `node`, or null outside of one
        const declaredAt = (node) => {
            const override = overrides().find(({ options }) => options.range[0] <= node.range[0] && node.range[1] <= options.range[1]);

            return override ? declaredIn(override) : null;
        };

        function rewriteCall(call, name, scope, container) {
            const path = ACCESSORS[name];
            const [argument] = call.arguments;
            const optional = path.includes('?.');

            if (call.arguments.length !== 1 || !isPlainReference(argument) || call.optional || (optional && isWriteTarget(call, container))) {
                todo(call, accessorMessage(name), scope);

                return;
            }

            const replacement = `${sourceCode.getText(argument)}.${path}`;
            const parent = call.parent;

            context.report({
                loc: locOf(sourceCode, call),
                message: accessorMessage(name),
                fix: (fixer) => {
                    // `getDeliveryState(item).translated` → `item.primaryOrderDelivery?.stateMachineState?.translated`
                    if (optional && parent.type === 'MemberExpression' && parent.object === call && !parent.optional) {
                        return parent.computed
                            ? fixer.replaceText(call, `${replacement}?.`)
                            : fixer.replaceTextRange([call.range[0], parent.property.range[0]], `${replacement}?.`);
                    }

                    return fixer.replaceText(call, replacement);
                },
            });
        }

        const markedAssociations = new Set();
        let addedAssociations = null;
        const scriptAddsAssociation = (association) => {
            if (addedAssociations === null) {
                addedAssociations = new Set();
                walk(sourceCode.ast, (node) => {
                    const method = node.type === 'CallExpression' && node.callee.type === 'MemberExpression' ? node.callee.property.name : null;
                    const value = ['addAssociation', 'getAssociation'].includes(method) ? stringValue(node.arguments[0]) : null;

                    if (value) {
                        addedAssociations.add(value.split('.')[0]);
                    }
                });
            }

            return addedAssociations.has(association);
        };

        const scriptVisitors = {
            CallExpression(node) {
                if (node.callee.type !== 'MemberExpression') {
                    return;
                }

                const name = thisMemberName(node.callee);
                const declared = name ? declaredAt(node) : null;

                if (!declared) {
                    return;
                }

                if (name === '$super') {
                    const superName = stringValue(node.arguments[0]);

                    if (ACCESSORS[superName] || REMOVED[superName] || SUPER_ONLY[superName]) {
                        todo(node, superMessage(superName), 'script');
                    }

                    return;
                }

                if (declared.has(name)) {
                    return;
                }

                if (ACCESSORS[name]) {
                    rewriteCall(node, name, 'script');
                } else if (REMOVED[name]) {
                    todo(node, REMOVED[name], 'script');
                }
            },
            MemberExpression(node) {
                const name = thisMemberName(node);
                const isCallee = node.parent.type === 'CallExpression' && node.parent.callee === node;

                if (name && !isCallee && (ACCESSORS[name] || REMOVED[name])) {
                    const declared = declaredAt(node);

                    if (declared && !declared.has(name)) {
                        todo(node, ACCESSORS[name] ? accessorMessage(name) : REMOVED[name], 'script');
                    }

                    return;
                }

                const association = associationName(node);

                if (!association || !declaredAt(node) || scriptAddsAssociation(association)) {
                    return;
                }

                // One comment per association and method is enough to find the code that reads it
                const method = enclosingFunction(node);
                const key = `${method?.range[0]}:${association}`;

                if (!markedAssociations.has(key)) {
                    markedAssociations.add(key);
                    todo(node, associationMessage(association), 'script');
                }
            },
            VariableDeclarator(node) {
                for (const member of destructuredThisMembers(node)) {
                    const declared = ACCESSORS[member.name] || REMOVED[member.name] ? declaredAt(node) : null;

                    if (declared && !declared.has(member.name)) {
                        todo(member.node, ACCESSORS[member.name] ? accessorMessage(member.name) : REMOVED[member.name], 'script');
                    }
                }
            },
        };

        if (!sourceCode.parserServices?.defineTemplateBodyVisitor) {
            return scriptVisitors;
        }

        const twig = isTwigFile(context);
        let templateContext = null;
        // What the template needs to know about the script that owns it
        const owner = () => {
            if (templateContext) {
                return templateContext;
            }

            if (twig) {
                const scripts = importingScripts(context);
                const added = new Set(scripts.flatMap(({ source }) => [...source.matchAll(ASSOCIATION_CALL)].map((match) => match[2])));

                templateContext = {
                    overrides: templateTargets(context).includes(COMPONENT),
                    declares: (name) => scriptsDeclare(scripts, name),
                    adds: (association) => added.has(association),
                };
            } else {
                const declared = new Set(overrides().flatMap((override) => [...declaredIn(override)]));

                templateContext = {
                    overrides: fileTargets(context).includes(COMPONENT),
                    declares: (name) => declared.has(name),
                    adds: scriptAddsAssociation,
                };
            }

            return templateContext;
        };

        function checkTemplateReferences(references, container) {
            for (const identifier of references) {
                const name = identifier.name;

                if ((!ACCESSORS[name] && !REMOVED[name]) || owner().declares(name)) {
                    continue;
                }

                const parent = identifier.parent;

                if (ACCESSORS[name] && parent.type === 'CallExpression' && parent.callee === identifier) {
                    rewriteCall(parent, name, 'template', container);
                } else {
                    todo(identifier, ACCESSORS[name] ? accessorMessage(name) : REMOVED[name], 'template');
                }
            }
        }

        function checkTemplateAssociations(expression) {
            walk(expression, (node) => {
                const association = associationName(node);

                if (association && !owner().adds(association)) {
                    todo(node, associationMessage(association), 'template');
                }
            });
        }

        const templateVisitors = {
            VExpressionContainer(node) {
                if (node.expression && owner().overrides) {
                    checkTemplateReferences(templateMemberReferences(node), node);
                    checkTemplateAssociations(node.expression);
                }
            },
        };

        if (twig) {
            // `{{ … }}` is text in `.html.twig` templates
            templateVisitors.VText = (node) => {
                if (!owner().overrides) {
                    return;
                }

                for (const { container, expression, references } of twigInterpolations(sourceCode, node)) {
                    checkTemplateReferences(references, container);
                    checkTemplateAssociations(expression);
                }
            };
        }

        return sourceCode.parserServices.defineTemplateBodyVisitor(templateVisitors, scriptVisitors);
    },
};
