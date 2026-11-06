/**
 * @sw-package framework
 *
 * Builds the upgrade codemods that only write a `TODO Codemod:` comment with the replacement
 * above each spot that uses a removed or changed Administration API. Each rule is described
 * by matchers, every matcher carries the hint that goes into the comment.
 *
 * Script matchers (JavaScript, TypeScript and the script of `.vue` files):
 * - `member`: `<object>.<property>`, e.g. `Shopware.State` or `this.isLoading`.
 *   `object` is a dotted path, `this`, or omitted for any object. Instead of `object`, `service`
 *   names a service (`this.x`, `Shopware.Service('x')`) and `store` a Pinia store (`Shopware.Store.get('x')`).
 *   `object: '*.name'` matches any path that ends with `name`. `filter(node)` narrows the match further.
 *   Also matches the property destructured from that object, e.g. `const { State } = Shopware`.
 * - `option`: a member an override defines, e.g. `computed: { productStates() {} }`.
 *   `sections` defaults to `computed`, `methods`, `watch` and `props`.
 * - `key`: a key of any object literal, e.g. `{ cancelToken: source.token }`.
 * - `inject`: an injected key, in `inject: [...]`, `inject: { local: 'key' }` or `inject('key')`.
 * - `component`: `Component.override()` or `Component.extend()` of one of the components.
 * - `string`: a string literal with one of the values, e.g. a component name in a rule condition config.
 * - `import`: an import or `require()` whose source matches, optionally only for some specifiers.
 * - `file`: a file whose name matches `pattern`, e.g. a `vite.config.js` of the extension.
 *
 * Template matchers (`.html.twig` files and the template of `.vue` files):
 * - `element`: a tag, e.g. `<sw-text-editor>`.
 * - `attribute`: an attribute, prop binding or listener, optionally only on some tags.
 * - `block`: an override of a Twig block, `{% block name %}` or `<sw-block extends="name">`.
 * - `identifier`: a component member used in a template expression, e.g. `isSaveSuccessful`.
 *
 * `member`, `option`, `inject` and `identifier` take an optional `components` list. The spot then only counts
 * inside `Component.override/extend()` of one of them, or in a file whose folder or name is one of
 * them, the convention for override files such as `sw-order-detail/index.js`.
 */

const { createTodoReporter, todoMessages } = require('./todo-comment');
const { mustacheMatches, referencePattern } = require('./twig-mustache');
const { kebabCase } = require('./template-elements');
const path = require('path');
const {
    componentFactoryCall,
    componentOptions,
    dottedPath,
    isInComponents,
    isService,
    isStore,
    propertyName,
    staticString,
} = require('./component-context');

const DEFAULT_OPTION_SECTIONS = ['computed', 'methods', 'watch', 'props'];

/**
 * @param {{ components?: string[] }} matcher
 * @param {import('eslint').Rule.RuleContext} context
 * @param {import('eslint').Rule.Node | null} node
 */
function inComponents(matcher, context, node) {
    return !matcher.components || isInComponents(context, node, matcher.components);
}

/**
 * @param {string | string[] | undefined} value
 * @returns {string[] | undefined}
 */
function list(value) {
    if (value === undefined) {
        return undefined;
    }

    return Array.isArray(value) ? value : [value];
}

/** @param {import('eslint').Rule.Node} node */
function isPropertyKeyOrMemberName(node) {
    const parent = node.parent;

    if (parent?.type === 'MemberExpression' && parent.property === node && !parent.computed) {
        return true;
    }

    return parent?.type === 'Property' && parent.key === node && !parent.computed && !parent.shorthand;
}

/**
 * @param {import('eslint').Rule.RuleContext} context
 * @param {ReturnType<typeof createTodoReporter>} reporter
 * @param {object[]} matchers
 */
function scriptVisitor(context, reporter, matchers) {
    const byType = (type) => matchers.filter((matcher) => matcher.type === type);
    const members = byType('member');
    const injects = byType('inject');
    const components = byType('component');
    const imports = byType('import');
    const strings = byType('string');
    const options = byType('option');
    const keys = byType('key');
    const files = byType('file');
    const hasReceiver = (matcher) => [matcher.object, matcher.service, matcher.store].some((value) => value !== undefined);

    const matchesObject = (matcher, objectNode) => {
        if (matcher.service !== undefined) {
            return isService(objectNode, matcher.service);
        }

        if (matcher.store !== undefined) {
            return isStore(objectNode, matcher.store);
        }

        if (matcher.object?.startsWith('*.')) {
            const objectPath = dottedPath(objectNode);
            const suffix = matcher.object.slice(2);

            return objectPath === suffix || objectPath?.endsWith(`.${suffix}`) === true;
        }

        return matcher.object === undefined || dottedPath(objectNode) === matcher.object;
    };

    /**
     * `computed: { name() {} }` and the like in the options object of a component.
     *
     * @param {import('eslint').Rule.Node} node
     */
    const checkOption = (node) => {
        const section = node.parent.parent;
        const sectionName = section?.type === 'Property' ? propertyName(section) : null;

        if (!sectionName || componentOptions(section.parent) !== section.parent) {
            return;
        }

        const name = propertyName(node);

        options
            .filter((matcher) => (matcher.sections ?? DEFAULT_OPTION_SECTIONS).includes(sectionName))
            .filter((matcher) => matcher.names.includes(name))
            .filter((matcher) => inComponents(matcher, context, node))
            .forEach((matcher) => reporter.reportScript(node, matcher.hint));
    };

    const checkInjectKey = (keyNode, reportNode) => {
        const key = staticString(keyNode);

        injects
            .filter((matcher) => key !== null && matcher.names.includes(key))
            .filter((matcher) => inComponents(matcher, context, reportNode))
            .forEach((matcher) => reporter.reportScript(reportNode, matcher.hint));
    };

    const checkImport = (source, specifiers, node) => {
        imports
            .filter((matcher) => {
                const sources = list(matcher.source);

                return source !== null && sources.some((candidate) => source === candidate || source.endsWith(`/${candidate}`));
            })
            .filter((matcher) => !matcher.specifiers || specifiers.some((name) => matcher.specifiers.includes(name)))
            .forEach((matcher) => reporter.reportScript(node, matcher.hint));
    };

    return {
        MemberExpression(node) {
            const name = propertyName(node);

            members
                .filter((matcher) => name !== null && list(matcher.properties).includes(name))
                .filter((matcher) => matchesObject(matcher, node.object))
                .filter((matcher) => inComponents(matcher, context, node))
                .filter((matcher) => !matcher.filter || matcher.filter(node))
                .forEach((matcher) => reporter.reportScript(node, matcher.hint));
        },

        VariableDeclarator(node) {
            if (node.id.type !== 'ObjectPattern' || !node.init) {
                return;
            }

            node.id.properties
                .filter((property) => property.type === 'Property')
                .forEach((property) => {
                    const name = propertyName(property);

                    members
                        .filter((matcher) => list(matcher.properties).includes(name))
                        .filter((matcher) => !hasReceiver(matcher) || matchesObject(matcher, node.init))
                        .filter((matcher) => inComponents(matcher, context, node))
                        .forEach((matcher) => reporter.reportScript(property, matcher.hint));
                });
        },

        Program(node) {
            const filename = path.basename(context.physicalFilename ?? context.filename);

            files
                .filter((matcher) => matcher.pattern.test(filename))
                .forEach((matcher) => reporter.reportScript(node.body[0] ?? node, matcher.hint));
        },

        Property(node) {
            if (node.parent.type !== 'ObjectExpression') {
                return;
            }

            const name = propertyName(node);

            keys
                .filter((matcher) => matcher.names.includes(name))
                .forEach((matcher) => reporter.reportScript(node, matcher.hint));

            if (options.length > 0) {
                checkOption(node);
            }

            if (name !== 'inject') {
                return;
            }

            if (node.value.type === 'ArrayExpression') {
                node.value.elements.filter(Boolean).forEach((element) => checkInjectKey(element, element));

                return;
            }

            if (node.value.type !== 'ObjectExpression') {
                return;
            }

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
        },

        CallExpression(node) {
            if (node.callee.type === 'Identifier' && node.callee.name === 'inject' && node.arguments[0]) {
                checkInjectKey(node.arguments[0], node);
            }

            if (node.callee.type === 'Identifier' && node.callee.name === 'require' && node.arguments.length === 1) {
                checkImport(staticString(node.arguments[0]), [], node);
            }

            const call = componentFactoryCall(node);

            if (call && call.method !== 'register') {
                components
                    .filter((matcher) => matcher.names.includes(call.name))
                    .forEach((matcher) => reporter.reportScript(node, matcher.hint));
            }
        },

        Literal(node) {
            if (typeof node.value !== 'string' || node.parent.type === 'ImportDeclaration') {
                return;
            }

            strings
                .filter((matcher) => matcher.values.includes(node.value))
                .forEach((matcher) => reporter.reportScript(node, matcher.hint));
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
}

/**
 * @param {import('eslint').Rule.RuleContext} context
 * @param {ReturnType<typeof createTodoReporter>} reporter
 * @param {object[]} matchers
 */
function templateVisitor(context, reporter, matchers) {
    const byType = (type) => matchers.filter((matcher) => matcher.type === type);
    const elements = byType('element');
    const attributes = byType('attribute');
    const blocks = byType('block');
    const identifiers = byType('identifier');

    const attributeName = (attribute) => {
        if (!attribute.directive) {
            return kebabCase(attribute.key.rawName ?? attribute.key.name);
        }

        const directive = attribute.key.name.name;
        const argument = attribute.key.argument;

        if (!['bind', 'on', 'model'].includes(directive) || argument?.type !== 'VIdentifier') {
            return null;
        }

        const name = kebabCase(argument.rawName ?? argument.name);

        return directive === 'on' ? `@${name}` : name;
    };

    const tagNames = (node) => [node.rawName, node.name, kebabCase(node.rawName)];
    const matchesTag = (names, node) => tagNames(node).some((name) => names.includes(name));
    let blocksScanned = false;

    // `{% block %}` tags are plain text to the Vue parser, so the Twig source is scanned once.
    const scanTwigBlocks = () => {
        blocksScanned = true;

        if (blocks.length === 0) {
            return;
        }

        const blockTag = /\{%-?\s*block\s+([\w.-]+)\s*-?%\}/g;

        for (const match of context.sourceCode.text.matchAll(blockTag)) {
            blocks
                .filter((matcher) => matcher.names.includes(match[1]))
                .forEach((matcher) => reporter.reportTwig(match.index, matcher.hint));
        }
    };

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

    return {
        VElement(node) {
            if (!blocksScanned) {
                scanTwigBlocks();
            }

            elements
                .filter((matcher) => matchesTag(matcher.names, node))
                .forEach((matcher) => reporter.reportTemplate(node, matcher.hint));

            attributes
                .filter((matcher) => !matcher.elements || matchesTag(matcher.elements, node))
                .forEach((matcher) => {
                    const used = node.startTag.attributes.some((attribute) => matcher.names.includes(attributeName(attribute)));

                    if (used) {
                        reporter.reportTemplate(node, matcher.hint);
                    }
                });

            if (node.name === 'sw-block') {
                const extendsAttribute = node.startTag.attributes.find((attribute) => {
                    return !attribute.directive && attribute.key.name === 'extends';
                });
                const blockName = extendsAttribute?.value?.value;

                blocks
                    .filter((matcher) => blockName && matcher.names.includes(blockName))
                    .forEach((matcher) => reporter.reportTemplate(node, matcher.hint));
            }
        },

        // `{{ … }}` in `.html.twig` files, which the Vue parser keeps as text.
        VText(node) {
            identifiers
                .filter((matcher) => inComponents(matcher, context, null))
                .filter((matcher) => mustacheMatches(context.sourceCode, node, referencePattern(matcher.names)).length > 0)
                .forEach((matcher) => reporter.reportTemplate(node.parent, matcher.hint));
        },

        Identifier(node) {
            if (isPropertyKeyOrMemberName(node) || isTemplateVariable(node)) {
                return;
            }

            identifiers
                .filter((matcher) => matcher.names.includes(node.name))
                .filter((matcher) => inComponents(matcher, context, null))
                .forEach((matcher) => {
                    const element = enclosingElement(node);

                    if (element) {
                        reporter.reportTemplate(element, matcher.hint);
                    }
                });
        },
    };
}

/**
 * @param {{ description: string, script?: object[], template?: object[] }} spec
 * @returns {import('eslint').Rule.RuleModule}
 */
function createTodoRule(spec) {
    const scriptMatchers = spec.script ?? [];
    const templateMatchers = spec.template ?? [];

    return {
        meta: {
            type: 'problem',
            docs: {
                description: spec.description,
                category: 'Shopware 6.8 Migration',
                recommended: false,
            },
            fixable: 'code',
            schema: [],
            messages: todoMessages,
        },
        create(context) {
            const reporter = createTodoReporter(context);
            const script = scriptVisitor(context, reporter, scriptMatchers);
            const parserServices = context.sourceCode.parserServices;

            if (templateMatchers.length === 0 || !parserServices?.defineTemplateBodyVisitor) {
                return script;
            }

            return parserServices.defineTemplateBodyVisitor(templateVisitor(context, reporter, templateMatchers), script);
        },
    };
}

module.exports = {
    createTodoRule,
};
