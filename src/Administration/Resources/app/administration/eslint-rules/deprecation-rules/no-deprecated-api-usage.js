/**
 * @sw-package framework
 */

const path = require('path');
const { loadRegistry } = require('./registry/load-registry');
const { selectedUsages } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');
const { canAutoFix } = require('./registry/fix-policy');
const { withRunLevel } = require('./registry/run-level');
const {
    componentContext,
    componentFactoryCall,
    componentOptions,
    definesMember,
    dottedPath,
    isService,
    isStore,
    isSuperCallOf,
    propertyName,
    staticString,
} = require('./registry/component-scope');
const { mustacheMatches, referencePattern } = require('./registry/twig-mustache');

const API_USAGE_KINDS = new Set([
    'member-usage',
    'service-method',
    'store-member',
    'component-member',
    'inject-key',
    'component-reference',
    'string-value',
    'object-key',
    'import-source',
    'config-file',
]);

// An override that defines one of these keeps the member for itself.
const MEMBER_SECTIONS = ['computed', 'methods', 'data', 'props', 'inject'];
const OPTION_SECTIONS = ['computed', 'methods', 'watch', 'props'];

/** The name a report uses for the deprecated API of a step. */
function apiLabel(usage, component) {
    switch (usage.kind) {
        case 'service-method':
            return `${usage.service}.${usage.method}()`;
        case 'store-member':
            return `Shopware.Store.get('${usage.store}').${usage.member}`;
        case 'component-member':
            return `${component ?? usage.components[0]}.${usage.member}`;
        case 'component-reference':
            return usage.component;
        case 'config-file':
            return usage.pattern;
        default:
            return usage.from;
    }
}

function buildMessage(migration, usage, component) {
    const replacement = usage.to ? ` Use ${usage.to} instead.` : '';

    return appendRegistryContext(
        [`The "${apiLabel(usage, component)}" API is deprecated.${replacement}`, usage.message ?? ''],
        migration,
    );
}

function kebabCase(name) {
    return name.replace(/([a-z0-9])([A-Z])/g, '$1-$2').toLowerCase();
}

/**
 * Tells whether a member chain matches a `member-usage` path: `Shopware.State`, `this.$store`, or
 * `*.CancelToken` for the member on any object.
 *
 * @example
 * matchesMemberPath('*.CancelToken', 'CancelToken', axiosNode); // true
 */
function matchesMemberPath(from, name, objectNode) {
    const separator = from.lastIndexOf('.');
    const objectPath = from.slice(0, separator);

    if (from.slice(separator + 1) !== name) {
        return false;
    }

    if (objectPath === '*') {
        return true;
    }

    const actualPath = dottedPath(objectNode);

    // `*.mailTemplateType.templateData` matches the member on any object path ending with `mailTemplateType`
    if (objectPath.startsWith('*.')) {
        const suffix = objectPath.slice(2);

        return actualPath === suffix || actualPath?.endsWith(`.${suffix}`) === true;
    }

    return actualPath === objectPath;
}

/**
 * Tells whether a `service-method` step reports this member access: every access, or with
 * `missingArgument` only a call that leaves the argument out or passes `null` or `undefined`.
 */
function leavesOutArgument(usage, memberNode) {
    if (usage.missingArgument === undefined) {
        return true;
    }

    const call = memberNode.parent;

    if (call.type !== 'CallExpression' || call.callee !== memberNode) {
        return false;
    }

    const argument = call.arguments[usage.missingArgument];

    return !argument
        || (argument.type === 'Literal' && argument.value === null)
        || (argument.type === 'Identifier' && argument.name === 'undefined');
}

/**
 * This rule reports the deprecated JavaScript and template APIs of the registry that need no
 * component conversion: members of global APIs, services, stores and components, injected keys,
 * references of removed components, string values, object keys, imports and config files.
 * It fixes the steps that name a replacement and are not `manual`.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = withRunLevel({
    meta: {
        type: 'suggestion',
        docs: {
            description: 'No usage of deprecated Administration APIs',
            recommended: true,
        },
        fixable: 'code',
        schema: [],
    },
    create(context) {
        const usages = selectedUsages(loadRegistry().jsApiMigrations).filter(({ usage }) => API_USAGE_KINDS.has(usage.kind));
        const ofKind = (kind) => usages.filter(({ usage }) => usage.kind === kind);
        const sourceCode = context.sourceCode;
        const filename = context.physicalFilename ?? context.filename;

        if (usages.length === 0) {
            return {};
        }

        const report = ({ migration, usage }, node, { component, fix } = {}) => {
            context.report({
                node,
                message: buildMessage(migration, usage, component),
                fixLevel: usage.fix,
                fix: fix && canAutoFix(context, usage) ? fix : null,
            });
        };

        /** The listed component a spot belongs to, or undefined. */
        const componentOf = (usage, node) => componentContext(context, node).find((name) => usage.components.includes(name));

        const replaceLiteral = (node, value) => (fixer) => {
            const quote = sourceCode.getText(node)[0];

            return fixer.replaceText(node, `${quote}${value}${quote}`);
        };

        const checkImport = (source, specifiers, node) => {
            ofKind('import-source')
                .filter(({ usage }) => source !== null && (source === usage.from || source.endsWith(`/${usage.from}`)))
                .filter(({ usage }) => !usage.specifiers || specifiers.some((name) => usage.specifiers.includes(name)))
                .forEach((match) => report(match, node));
        };

        const checkInjectKey = (keyNode, reportNode) => {
            const key = staticString(keyNode);

            ofKind('inject-key')
                .filter(({ usage }) => usage.from === key)
                .forEach((match) => report(match, reportNode));
        };

        /** `this.<member>`, also inside `this.$super('<member>')`, in an override of a listed component. */
        const checkComponentMember = (node, name) => {
            ofKind('component-member')
                .filter(({ usage }) => usage.member === name)
                .forEach((match) => {
                    const component = componentOf(match.usage, node);
                    const replacement = match.usage.replaceInScript;

                    if (!component) {
                        return;
                    }

                    if (replacement && definesMember(componentOptions(node), MEMBER_SECTIONS, name)) {
                        return;
                    }

                    report(match, node, {
                        component,
                        fix: replacement && !node.computed ? (fixer) => fixer.replaceText(node, replacement) : null,
                    });
                });
        };

        const scriptVisitor = {
            Program(node) {
                const basename = path.basename(filename);

                ofKind('config-file')
                    .filter(({ usage }) => new RegExp(usage.pattern).test(basename))
                    .forEach((match) => report(match, node.body[0] ?? node));
            },

            MemberExpression(node) {
                const name = propertyName(node);

                if (name === null) {
                    return;
                }

                ofKind('member-usage')
                    .filter(({ usage }) => matchesMemberPath(usage.from, name, node.object))
                    .forEach((match) => report(match, node));

                ofKind('service-method')
                    .filter(({ usage }) => usage.method === name && isService(node.object, usage.service))
                    .filter(({ usage }) => leavesOutArgument(usage, node))
                    .forEach((match) => report(match, node));

                ofKind('store-member')
                    .filter(({ usage }) => usage.member === name && isStore(node.object, usage.store))
                    .forEach((match) => report(match, node));

                const isAssignment = node.parent.type === 'AssignmentExpression' && node.parent.left === node;

                if (node.object.type === 'ThisExpression' && !isAssignment) {
                    checkComponentMember(node, name);
                }
            },

            CallExpression(node) {
                const superMember = ofKind('component-member').find(({ usage }) => isSuperCallOf(node, [usage.member]));

                if (superMember) {
                    const component = componentOf(superMember.usage, node);
                    const replacement = superMember.usage.replaceInScript;

                    if (component) {
                        report(superMember, node, {
                            component,
                            fix: replacement ? (fixer) => fixer.replaceText(node, replacement) : null,
                        });
                    }
                }

                if (node.callee.type === 'Identifier' && node.callee.name === 'inject' && node.arguments[0]) {
                    checkInjectKey(node.arguments[0], node);
                }

                if (node.callee.type === 'Identifier' && node.callee.name === 'require' && node.arguments.length === 1) {
                    checkImport(staticString(node.arguments[0]), [], node);
                }

                const call = componentFactoryCall(node);

                if (call && call.method !== 'register') {
                    ofKind('component-reference')
                        .filter(({ usage }) => usage.component === call.name)
                        .forEach((match) => report(match, node));
                }
            },

            VariableDeclarator(node) {
                if (node.id.type !== 'ObjectPattern' || !node.init) {
                    return;
                }

                node.id.properties
                    .filter((property) => property.type === 'Property')
                    .forEach((property) => {
                        const name = propertyName(property);

                        ofKind('member-usage')
                            .filter(({ usage }) => matchesMemberPath(usage.from, name, node.init))
                            .forEach((match) => report(match, property));

                        if (node.init.type === 'ThisExpression') {
                            checkComponentMember(property, name);
                        }
                    });
            },

            Property(node) {
                if (node.parent.type !== 'ObjectExpression') {
                    return;
                }

                const name = propertyName(node);

                ofKind('object-key')
                    .filter(({ usage }) => usage.from === name)
                    .forEach((match) => report(match, node));

                // A member the override defines, e.g. `computed: { productStates() {} }`
                const section = node.parent.parent;
                const sectionName = section?.type === 'Property' ? propertyName(section) : null;

                if (OPTION_SECTIONS.includes(sectionName) && componentOptions(section.parent) === section.parent) {
                    ofKind('component-member')
                        .filter(({ usage }) => usage.member === name && !usage.replaceInScript)
                        .forEach((match) => {
                            const component = componentOf(match.usage, node);

                            if (component) {
                                report(match, node, { component });
                            }
                        });
                }

                if (name !== 'inject') {
                    return;
                }

                if (node.value.type === 'ArrayExpression') {
                    node.value.elements.filter(Boolean).forEach((element) => checkInjectKey(element, element));

                    return;
                }

                if (node.value.type === 'ObjectExpression') {
                    node.value.properties
                        .filter((property) => property.type === 'Property')
                        .forEach((property) => {
                            const fromProperty = property.value.type === 'ObjectExpression'
                                ? property.value.properties.find((candidate) => propertyName(candidate) === 'from')
                                : null;
                            const keyNode = fromProperty?.value
                                ?? (staticString(property.value) !== null ? property.value : null)
                                ?? { type: 'Literal', value: propertyName(property) };

                            checkInjectKey(keyNode, property);
                        });
                }
            },

            Literal(node) {
                if (typeof node.value !== 'string' || node.parent.type === 'ImportDeclaration') {
                    return;
                }

                ofKind('string-value')
                    .filter(({ usage }) => usage.from === node.value)
                    .forEach((match) => report(match, node, { fix: match.usage.to ? replaceLiteral(node, match.usage.to) : null }));

                // The name in `Component.override('<name>')` belongs to the call, which is reported itself.
                if (node.parent.type === 'CallExpression' && componentFactoryCall(node.parent)) {
                    return;
                }

                ofKind('component-reference')
                    .filter(({ usage }) => usage.component === node.value)
                    .forEach((match) => report(match, node));
            },

            ImportDeclaration(node) {
                const specifiers = node.specifiers.map((specifier) => {
                    return specifier.type === 'ImportSpecifier' ? (specifier.imported.name ?? specifier.imported.value) : 'default';
                });

                checkImport(node.source.value, specifiers, node);
            },

            ImportExpression(node) {
                checkImport(staticString(node.source), [], node);
            },
        };

        const defineTemplateBodyVisitor = sourceCode.parserServices?.defineTemplateBodyVisitor;

        if (!defineTemplateBodyVisitor) {
            return scriptVisitor;
        }

        const enclosingElement = (node) => {
            let current = node.parent;

            while (current && current.type !== 'VElement') {
                current = current.parent;
            }

            return current;
        };

        // A `v-for` alias or slot prop of the same name is no component member.
        const isTemplateVariable = (node) => {
            for (let element = enclosingElement(node); element; element = element.parent) {
                if (element.variables?.some((variable) => variable.id.name === node.name)) {
                    return true;
                }
            }

            return false;
        };

        const templateMember = (match) => {
            const component = componentOf(match.usage, null);

            return component ? { component } : null;
        };

        const templateVisitor = {
            VElement(node) {
                ofKind('component-reference')
                    .filter(({ usage }) => usage.templates !== false)
                    .filter(({ usage }) => [node.rawName, node.name, kebabCase(node.rawName)].includes(usage.component))
                    .forEach((match) => report(match, node));
            },

            Identifier(node) {
                const parent = node.parent;
                const isMemberName = parent.type === 'MemberExpression' && parent.property === node && !parent.computed;

                if (isMemberName || isTemplateVariable(node)) {
                    return;
                }

                ofKind('component-member')
                    .filter(({ usage }) => usage.member === node.name)
                    .forEach((match) => {
                        const scope = templateMember(match);

                        if (scope) {
                            report(match, node, scope);
                        }
                    });
            },

            Literal(node) {
                if (typeof node.value !== 'string') {
                    return;
                }

                ofKind('string-value')
                    .filter(({ usage }) => usage.from === node.value)
                    .forEach((match) => report(match, node, { fix: match.usage.to ? replaceLiteral(node, match.usage.to) : null }));
            },

            // `{{ … }}` in `.html.twig` files, which the Vue parser keeps as text.
            VText(node) {
                ofKind('component-member').forEach((match) => {
                    const scope = templateMember(match);

                    if (scope && mustacheMatches(sourceCode, node, referencePattern([match.usage.member])).length > 0) {
                        report(match, node, scope);
                    }
                });
            },
        };

        return defineTemplateBodyVisitor(templateVisitor, scriptVisitor);
    },
});
