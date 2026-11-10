/**
 * @sw-package framework
 *
 * Reports extension code that uses Administration APIs the registry lists as removed or changed
 * (`apiUsageMigrations`), in scripts (JavaScript, TypeScript, the script of `.vue` files) and in
 * templates (`.vue` templates and `.html.twig` through the twig-vue processor). The `replace*` and
 * `module-snippets` steps fix the code; every other step is reported, and at the `partial` and
 * `manual` run levels gets a review comment with its message.
 *
 * Steps with `components` only match in overrides and extensions of those components:
 * - script spots inside `Component.override()` or `Component.extend()` of one of them, or in its
 *   native override `<component>.override.vue`. `Component.register()` defines a component of its
 *   own, so its members never count.
 * - template spots in such an `.override.vue`, or in a `.html.twig` that belongs to one of them,
 *   see `fileComponents()` in `registry/component-scope.js`.
 */

const espree = require('espree');
const path = require('path');
const { loadRegistry } = require('./registry/load-registry');
const { selectedUsages } = require('./registry/filter-migrations');
const { appendRegistryContext } = require('./registry/format-migration-message');
const { FIX_OPTION_SCHEMA, canAutoFix } = require('./registry/fix-policy');
const { withRunLevel } = require('./registry/run-level');
const { analyzeModuleSnippets } = require('./registry/module-snippets');
const {
    componentFactoryCall,
    componentOptions,
    componentsAt,
    definesMember,
    dottedPath,
    fileOwnMembers,
    isAdministrationSource,
    isService,
    isStore,
    overrideFileComponent,
    propertyName,
    sectionMembers,
    siblingIndexDefines,
    staticString,
    unwrapTypeScript,
} = require('./registry/component-scope');
const {
    expressionContainerOf,
    isTemplateLocal,
    isTemplateReference,
    matchesElementName,
    matchesTemplateAttribute,
    parseMustaches,
    walkExpression,
} = require('./registry/template-expressions');

// Option sections whose keys use or define a member of the component; `watch` keys use one.
const MEMBER_SECTIONS = [
    'computed',
    'methods',
    'watch',
    'props',
    'data',
    'inject',
];

// Option sections that define a member, so `this.<name>` reads the override's own member.
const OWN_MEMBER_SECTIONS = [
    'computed',
    'methods',
    'props',
    'data',
    'inject',
];

// Arguments that need no parentheses in front of a member access.
const PLAIN_ARGUMENT_TYPES = new Set([
    'Identifier',
    'MemberExpression',
    'CallExpression',
    'ThisExpression',
]);

// Replacements that need no parentheses wherever the replaced call stood.
const PLAIN_REPLACEMENT_TYPES = new Set([
    ...PLAIN_ARGUMENT_TYPES,
    'ChainExpression',
    'Literal',
    'TemplateLiteral',
    'ArrayExpression',
]);

const TYPESCRIPT_WRAPPERS = new Set([
    'TSAsExpression',
    'TSSatisfiesExpression',
    'TSNonNullExpression',
    'TSTypeAssertion',
]);

// Receivers that reach the same global object, e.g. `window.Shopware.State`.
const GLOBAL_OBJECTS = /^(?:window|globalThis|self)\./;

/**
 * Wraps a replacement in parentheses unless it is a member chain, call or literal, which can stand
 * wherever the replaced expression stood.
 *
 * @example
 * asExpression("Shopware.Filter.getByName('date')"); // unchanged
 * asExpression('a || b'); // '(a || b)'
 */
function asExpression(replacement) {
    try {
        const program = espree.parse(`(${replacement})`, { ecmaVersion: 'latest', sourceType: 'module' });
        const expression = program.body[0]?.expression;

        return PLAIN_REPLACEMENT_TYPES.has(expression?.type) ? replacement : `(${replacement})`;
    } catch {
        return `(${replacement})`;
    }
}

/**
 * The node an expression is used as: climbs over TypeScript wrappers like `!` and `as`.
 */
function outerExpression(node) {
    let current = node;

    while (current.parent && TYPESCRIPT_WRAPPERS.has(current.parent.type) && current.parent.expression === current) {
        current = current.parent;
    }

    return current;
}

/**
 * The call that `node` is the callee of, or null.
 *
 * @example
 * callOf(memberOf('this.getDelivery(order)')); // the call expression
 */
function callOf(node) {
    const callee = outerExpression(node);

    return callee.parent?.type === 'CallExpression' && callee.parent.callee === callee ? callee.parent : null;
}

function isNullish(node) {
    const value = unwrapTypeScript(node);

    return (
        (value?.type === 'Literal' && value.value === null && !value.regex) ||
        (value?.type === 'Identifier' && value.name === 'undefined') ||
        (value?.type === 'UnaryExpression' && value.operator === 'void')
    );
}

/**
 * Whether a call result is compared with `null` or `undefined` by `===` or `!==`, which a
 * replacement that can be `undefined` instead of `null` would change.
 */
function isComparedStrictlyWithNullish(call) {
    const node = outerExpression(call);
    const parent = node.parent;

    if (
        parent?.type !== 'BinaryExpression' ||
        ![
            '===',
            '!==',
        ].includes(parent.operator)
    ) {
        return false;
    }

    return isNullish(parent.left === node ? parent.right : parent.left);
}

/**
 * Whether `node` is the target of an assignment or update, e.g. `this.isLoading = true`.
 */
function isWritten(node) {
    const outer = outerExpression(node);
    const parent = outer.parent;

    return (
        (parent?.type === 'AssignmentExpression' && parent.left === outer) ||
        (parent?.type === 'UpdateExpression' && parent.argument === outer)
    );
}

/**
 * Whether a string literal is a value in the code, not an object key or a module path.
 */
function isStringValue(node) {
    const parent = node.parent;

    if (
        [
            'Property',
            'MethodDefinition',
            'PropertyDefinition',
            'TSPropertySignature',
            'TSEnumMember',
            'ImportAttribute',
        ].includes(parent?.type) &&
        (parent.key === node || parent.id === node)
    ) {
        return false;
    }

    if (
        [
            'ImportDeclaration',
            'ExportNamedDeclaration',
            'ExportAllDeclaration',
            'ImportExpression',
            'TSImportType',
            'TSExternalModuleReference',
        ].includes(parent?.type)
    ) {
        return false;
    }

    return !(
        parent?.type === 'CallExpression' &&
        parent.callee.type === 'Identifier' &&
        parent.callee.name === 'require' &&
        parent.arguments[0] === node
    );
}

/**
 * The names an `import()` or `require()` result is destructured into, or null when they are unknown.
 *
 * @example
 * destructuredNames(callOf("const { CancelToken } = require('axios')")); // ['CancelToken']
 */
function destructuredNames(node) {
    let current = node;

    if (current.parent?.type === 'AwaitExpression') {
        current = current.parent;
    }

    const declarator = current.parent;

    if (declarator?.type !== 'VariableDeclarator' || declarator.init !== current || declarator.id.type !== 'ObjectPattern') {
        return null;
    }

    return declarator.id.properties.map((property) => (property.type === 'Property' ? propertyName(property) : null)).filter(Boolean);
}

/**
 * The key a property of an `inject` object injects: `{ local: 'key' }`, `{ local: { from: 'key' } }`
 * or `{ key: { default: … } }`. Null for keys that are no string, such as symbols.
 */
function injectedKey(property) {
    const value = unwrapTypeScript(property.value);
    const key = staticString(value);

    if (key !== null) {
        return key;
    }

    if (value?.type !== 'ObjectExpression') {
        return null;
    }

    const from = value.properties.find((candidate) => candidate.type === 'Property' && propertyName(candidate) === 'from');

    return from ? staticString(from.value) : propertyName(property);
}

/**
 * Whether a module path is one of the step's sources, as a whole or as the end after a `/`.
 *
 * @example
 * matchesImportSource('src/core/factory/http.factory', ['src/core/factory/http.factory']); // true
 */
function matchesImportSource(source, sources) {
    return typeof source === 'string' && sources.some((candidate) => source === candidate || source.endsWith(`/${candidate}`));
}

/**
 * This rule reports extension code that uses Administration APIs the registry lists as removed or
 * changed, and rewrites the usages the registry has a replacement for.
 *
 * @type {import('eslint').Rule.RuleModule}
 */
module.exports = withRunLevel({
    meta: {
        type: 'problem',
        docs: {
            description: 'No usage of removed or changed Administration APIs',
            recommended: true,
        },
        fixable: 'code',
        schema: FIX_OPTION_SCHEMA,
    },

    create(context) {
        const usages = selectedUsages(loadRegistry().apiUsageMigrations);

        if (usages.length === 0) {
            return {};
        }

        const sourceCode = context.sourceCode;
        const filename = context.physicalFilename ?? context.filename;
        // The Administration's own files define the components, so they hold no overrides of removed ones.
        const isCoreFile = isAdministrationSource(filename);
        const of = (kind) => usages.filter(({ usage }) => usage.kind === kind);
        const memberUsages = of('member-usage');
        const componentMembers = of('component-member');
        const memberReplacements = of('replace-component-member');
        const methodCallReplacements = of('replace-component-method-call');
        const injectKeys = of('inject-key');
        const removedComponents = of('removed-component');
        const importSources = of('import-source');
        const stringLiterals = of('string-literal');
        const objectKeys = of('object-key');
        const templateAttributes = of('template-attribute');
        const moduleSnippets = of('module-snippets');
        const objectKeyFiles = new Map(objectKeys.map(({ usage }) => [usage, usage.files ? new RegExp(usage.files) : null]));
        const reported = new Set();
        const thisMemberNames = new Set([
            ...componentMembers.flatMap(({ usage }) => usage.names),
            ...memberReplacements.map(({ usage }) => usage.name),
            ...methodCallReplacements.map(({ usage }) => usage.name),
        ]);
        // Names a `.html.twig` mustache has to contain to be worth parsing.
        const mustacheNames = [
            ...memberUsages.flatMap(({ usage }) => usage.properties),
            ...thisMemberNames,
            ...stringLiterals.flatMap(({ usage }) => usage.values),
        ];
        let templateComponents = null;

        /**
         * Reports a spot once per message. With a fix, the step's fix level applies; without one, or
         * when the step may not be fixed, the spot is manual work and the run level writes the review
         * comment (`comment`, by default the step's message, otherwise the headline).
         */
        function report(match, target, headline, { fix = null, comment = null } = {}) {
            const { migration, usage } = match;
            const message = appendRegistryContext([headline], migration);
            const loc = target.loc ?? target.node.loc;
            const key = `${loc.start.line}:${loc.start.column}:${loc.end.line}:${loc.end.column}:${message}`;

            if (reported.has(key)) {
                return;
            }

            reported.add(key);

            if (fix && canAutoFix(context, usage)) {
                context.report({ ...target, message, fix, fixLevel: usage.fix, reviewComment: usage.message ?? headline });

                return;
            }

            context.report({ ...target, message, fixLevel: 'manual', reviewComment: comment ?? usage.message ?? headline });
        }

        /**
         * Where a script spot is reported. The run level puts its review comment at the start of the
         * line, so a spot whose line starts inside a multi-line string or template literal is
         * reported from the first line that starts with code.
         */
        function scriptTarget(node) {
            for (let current = node; current; current = current.parent) {
                const lineStart = sourceCode.getIndexFromLoc({ line: current.loc.start.line, column: 0 });
                const indentation = sourceCode.lines[current.loc.start.line - 1].match(/^\s*/)[0].length;

                if (sourceCode.getTokenByRangeStart(lineStart + indentation, { includeComments: true })) {
                    return current === node ? { node } : { loc: { start: current.loc.start, end: node.loc.end } };
                }
            }

            return { node };
        }

        /**
         * Where a template expression spot is reported: from the start of its `{{ … }}` or attribute
         * value, so a review comment never lands inside a multi-line expression, and a mustache right
         * inside a Twig block gets the comment above its own line.
         */
        function templateTarget(node) {
            const container = expressionContainerOf(node);

            return container ? { loc: { start: container.loc.start, end: node.loc.end } } : { node };
        }

        /** The components the template of this file overrides or extends. */
        function currentTemplateComponents() {
            templateComponents ??= componentsAt(context, null);

            return templateComponents;
        }

        /** The first component of a step the spot belongs to; null when it belongs to none. */
        function matchedComponent(usage, node) {
            const components = node ? componentsAt(context, node) : currentTemplateComponents();

            return components.find((name) => usage.components.includes(name)) ?? null;
        }

        function isInScope(usage, node) {
            return !usage.components || matchedComponent(usage, node) !== null;
        }

        /**
         * Whether the template of this file reads its own `name`: a binding of its `<script setup>`,
         * a member of its options, or a member the `index.js` next to a `.html.twig` defines.
         */
        function templateDefines(name) {
            if (filename.endsWith('.vue')) {
                return fileOwnMembers(sourceCode).has(name);
            }

            return filename.endsWith('.html.twig') && siblingIndexDefines(filename, name);
        }

        function defaultHeadline(api, replacement = null) {
            return `The "${api}" API is deprecated.${replacement ? ` Use ${replacement} instead.` : ''}`;
        }

        // member-usage

        /**
         * The paths a receiver is known by. In templates, `product.states` also reads `this.product`.
         */
        function receiverPaths(object, isTemplate) {
            const objectPath = dottedPath(object)?.replace(GLOBAL_OBJECTS, '');

            if (!objectPath) {
                return [];
            }

            let root = object;

            while (root.type === 'MemberExpression') {
                root = unwrapTypeScript(root.object);
            }

            const isImplicitThis = isTemplate && root.type === 'Identifier' && !isTemplateLocal(root);

            return isImplicitThis
                ? [
                      objectPath,
                      `this.${objectPath}`,
                  ]
                : [objectPath];
        }

        function matchesReceiver(usage, objectNode, isTemplate) {
            const object = unwrapTypeScript(objectNode);

            if (usage.service !== undefined) {
                return isService(object, usage.service);
            }

            if (usage.store !== undefined) {
                return isStore(object, usage.store);
            }

            if (usage.object === undefined) {
                return true;
            }

            const paths = receiverPaths(object, isTemplate);

            if (usage.object.startsWith('*.')) {
                const suffix = usage.object.slice(2);

                return paths.some((candidate) => candidate === suffix || candidate.endsWith(`.${suffix}`));
            }

            return paths.includes(usage.object);
        }

        /** For `withoutArgument`: whether the call of `node` leaves that argument out, or passes null or undefined. */
        function leavesOutArgument(usage, node) {
            if (usage.withoutArgument === undefined) {
                return true;
            }

            const call = callOf(node);

            if (!call) {
                return false;
            }

            const index = usage.withoutArgument;

            if (call.arguments.slice(0, index + 1).some((argument) => argument.type === 'SpreadElement')) {
                return false;
            }

            return call.arguments.length <= index || isNullish(call.arguments[index]);
        }

        function memberUsageHeadline(usage, name) {
            const receiver = usage.object ?? usage.service ?? (usage.store ? `Shopware.Store.get('${usage.store}')` : null);

            return usage.message ?? defaultHeadline(receiver ? `${receiver}.${name}` : name);
        }

        function checkMemberUsage(node, isTemplate) {
            const name = propertyName(node);

            if (name === null) {
                return;
            }

            memberUsages
                .filter(({ usage }) => usage.properties.includes(name))
                .filter(({ usage }) => matchesReceiver(usage, node.object, isTemplate))
                .filter(({ usage }) => isInScope(usage, isTemplate ? null : node) && leavesOutArgument(usage, node))
                .forEach((match) => {
                    report(match, isTemplate ? templateTarget(node) : scriptTarget(node), memberUsageHeadline(match.usage, name));
                });
        }

        // replace-component-method-call

        /**
         * Rewrites a call of a removed method, or reports it when it cannot: other arguments than
         * `with` takes, an optional call, or a strict comparison with null when the step says so.
         */
        function checkMethodCall(match, component, call, args, target) {
            const { usage } = match;
            const headline = usage.message ?? defaultHeadline(`${component}.${usage.name}()`, usage.with.replace('{0}', '…'));
            const needsArgument = usage.with.includes('{0}');
            const isRewritable =
                call &&
                !call.optional &&
                args.length === (needsArgument ? 1 : 0) &&
                args.every((argument) => argument.type !== 'SpreadElement');

            if (!isRewritable) {
                report(match, target, headline);

                return;
            }

            if (usage.strictNullMessage && isComparedStrictlyWithNullish(call)) {
                report(match, target, usage.strictNullMessage, { comment: usage.strictNullMessage });

                return;
            }

            const argument = args[0];
            const argumentText = argument && sourceCode.text.slice(argument.range[0], argument.range[1]);
            const argumentSource = argument && !PLAIN_ARGUMENT_TYPES.has(argument.type) ? `(${argumentText})` : argumentText;
            const replacement = asExpression(usage.with.replaceAll('{0}', argumentSource ?? ''));

            report(match, target, headline, { fix: (fixer) => fixer.replaceTextRange(call.range, replacement) });
        }

        // Script spots of component members: `this.<name>`, `this.$super('<name>')` and `const { <name> } = this`.

        function checkThisMember(node) {
            const name = propertyName(node);

            if (!thisMemberNames.has(name) || unwrapTypeScript(node.object).type !== 'ThisExpression') {
                return;
            }

            // `this.<name>` reads the override's own member when it defines one.
            if (definesMember(componentOptions(node), OWN_MEMBER_SECTIONS, name)) {
                return;
            }

            componentMembers
                .filter(({ usage }) => usage.names.includes(name))
                .forEach((match) => {
                    const component = matchedComponent(match.usage, node);

                    if (component) {
                        report(match, scriptTarget(node), match.usage.message ?? defaultHeadline(`${component}.${name}`));
                    }
                });

            memberReplacements
                .filter(({ usage }) => usage.name === name)
                .forEach((match) => {
                    const { usage } = match;
                    const component = matchedComponent(usage, node);

                    if (!component) {
                        return;
                    }

                    const headline = usage.message ?? defaultHeadline(`${component}.${name}`, usage.with);

                    if (isWritten(node)) {
                        report(match, scriptTarget(node), headline);

                        return;
                    }

                    report(match, scriptTarget(node), headline, {
                        fix: (fixer) => fixer.replaceTextRange(node.range, asExpression(usage.with)),
                    });
                });

            methodCallReplacements
                .filter(({ usage }) => usage.name === name)
                .forEach((match) => {
                    const component = matchedComponent(match.usage, node);
                    const call = callOf(node);

                    if (component) {
                        checkMethodCall(match, component, call, call?.arguments ?? [], scriptTarget(node));
                    }
                });
        }

        /** `this.$super('<name>', …)`: the component no longer has the member, also when the override defines it. */
        function checkSuperCall(node) {
            const callee = node.callee;

            if (
                callee.type !== 'MemberExpression' ||
                unwrapTypeScript(callee.object).type !== 'ThisExpression' ||
                propertyName(callee) !== '$super'
            ) {
                return;
            }

            const name = staticString(node.arguments[0]);

            componentMembers
                .filter(({ usage }) => usage.names.includes(name))
                .forEach((match) => {
                    const component = matchedComponent(match.usage, node);

                    if (component) {
                        report(match, scriptTarget(node), match.usage.message ?? defaultHeadline(`${component}.${name}`));
                    }
                });

            memberReplacements
                .filter(({ usage }) => usage.name === name)
                .forEach((match) => {
                    const { usage } = match;
                    const component = matchedComponent(usage, node);

                    if (component) {
                        report(match, scriptTarget(node), usage.message ?? defaultHeadline(`${component}.${name}`, usage.with), {
                            fix: (fixer) => fixer.replaceTextRange(node.range, asExpression(usage.with)),
                        });
                    }
                });

            methodCallReplacements
                .filter(({ usage }) => usage.name === name)
                .forEach((match) => {
                    const component = matchedComponent(match.usage, node);

                    if (component) {
                        checkMethodCall(match, component, node, node.arguments.slice(1), scriptTarget(node));
                    }
                });
        }

        /** `const { <name> } = this` in an override: a member the component lost. */
        function checkThisDestructuring(declarator) {
            const options = componentOptions(declarator);

            declarator.id.properties
                .filter((property) => property.type === 'Property')
                .forEach((property) => {
                    const name = propertyName(property);

                    if (name === null || definesMember(options, OWN_MEMBER_SECTIONS, name)) {
                        return;
                    }

                    [
                        ...componentMembers.filter(({ usage }) => usage.names.includes(name)),
                        ...memberReplacements.filter(({ usage }) => usage.name === name),
                        ...methodCallReplacements.filter(({ usage }) => usage.name === name),
                    ].forEach((match) => {
                        const component = matchedComponent(match.usage, property);

                        if (component) {
                            report(match, scriptTarget(property), match.usage.message ?? defaultHeadline(`${component}.${name}`));
                        }
                    });
                });
        }

        /** A component member the options of an override define, use as a watch key, or inject. */
        function checkOptionMembers(options) {
            options.properties
                .filter((section) => section.type === 'Property' && MEMBER_SECTIONS.includes(propertyName(section)))
                .flatMap(sectionMembers)
                .forEach((member) => {
                    componentMembers
                        .filter(({ usage }) => usage.names.includes(member.name))
                        .forEach((match) => {
                            const component = matchedComponent(match.usage, options);

                            if (component) {
                                report(
                                    match,
                                    scriptTarget(member.node),
                                    match.usage.message ?? defaultHeadline(`${component}.${member.name}`),
                                );
                            }
                        });
                });
        }

        // inject-key

        function checkInjectKey(key, node) {
            if (key === null) {
                return;
            }

            injectKeys
                .filter(({ usage }) => usage.names.includes(key) && isInScope(usage, node))
                .forEach((match) => report(match, scriptTarget(node), match.usage.message ?? defaultHeadline(`inject('${key}')`)));
        }

        function checkInjectOption(property) {
            const value = unwrapTypeScript(property.value);

            if (value?.type === 'ArrayExpression') {
                value.elements.filter(Boolean).forEach((element) => checkInjectKey(staticString(element), element));
            }

            if (value?.type === 'ObjectExpression') {
                value.properties
                    .filter((entry) => entry.type === 'Property')
                    .forEach((entry) => checkInjectKey(injectedKey(entry), entry));
            }
        }

        // import-source

        function checkImport(node, source, names) {
            importSources
                .filter(({ usage }) => matchesImportSource(source, usage.sources))
                .filter(({ usage }) => !usage.specifiers || (names ?? []).some((name) => usage.specifiers.includes(name)))
                .forEach((match) => report(match, scriptTarget(node), match.usage.message ?? defaultHeadline(source)));
        }

        function checkImportDeclaration(node) {
            if (node.importKind === 'type' || node.exportKind === 'type' || !node.source) {
                return;
            }

            const specifiers = node.specifiers ?? [];
            const runtimeSpecifiers = specifiers.filter((specifier) => {
                return specifier.importKind !== 'type' && specifier.exportKind !== 'type';
            });

            // A declaration of type-only specifiers needs nothing of the module at runtime.
            if (specifiers.length > 0 && runtimeSpecifiers.length === 0) {
                return;
            }

            const names = runtimeSpecifiers.map((specifier) => {
                if (specifier.type === 'ImportDefaultSpecifier') {
                    return 'default';
                }

                if (specifier.type === 'ImportNamespaceSpecifier') {
                    return '*';
                }

                const imported = specifier.type === 'ExportSpecifier' ? specifier.local : specifier.imported;

                return imported.name ?? imported.value;
            });

            checkImport(node, node.source.value, node.type === 'ExportAllDeclaration' ? null : names);
        }

        // string-literal

        function checkStringLiteral(node, isTemplate) {
            const value = staticString(node);

            if (value === null || !isStringValue(node)) {
                return;
            }

            stringLiterals
                .filter(({ usage }) => usage.values.includes(value) && isInScope(usage, isTemplate ? null : node))
                .forEach((match) => {
                    report(
                        match,
                        isTemplate ? templateTarget(node) : scriptTarget(node),
                        match.usage.message ?? defaultHeadline(value),
                    );
                });
        }

        // Template spots of component members: identifiers in template expressions.

        function checkTemplateIdentifier(node) {
            if (!isTemplateReference(node) || isTemplateLocal(node)) {
                return;
            }

            const name = node.name;
            const target = templateTarget(node);
            let ownDefinition = null;
            const isOwn = () => {
                ownDefinition ??= templateDefines(name);

                return ownDefinition;
            };

            // In templates `this` is implicit, so `$store` reads `this.$store`.
            memberUsages
                .filter(({ usage }) => usage.object === 'this' && usage.properties.includes(name))
                .filter(({ usage }) => isInScope(usage, null) && leavesOutArgument(usage, node) && !isOwn())
                .forEach((match) => report(match, target, memberUsageHeadline(match.usage, name)));

            componentMembers
                .filter(({ usage }) => usage.names.includes(name))
                .forEach((match) => {
                    const component = matchedComponent(match.usage, null);

                    if (component && !isOwn()) {
                        report(match, target, match.usage.message ?? defaultHeadline(`${component}.${name}`));
                    }
                });

            memberReplacements
                .filter(({ usage }) => usage.name === name)
                .forEach((match) => {
                    if (matchedComponent(match.usage, null) && !isOwn()) {
                        report(match, target, match.usage.templateMessage, { comment: match.usage.templateMessage });
                    }
                });

            methodCallReplacements
                .filter(({ usage }) => usage.name === name)
                .forEach((match) => {
                    const component = matchedComponent(match.usage, null);
                    const call = node.parent?.type === 'CallExpression' && node.parent.callee === node ? node.parent : null;

                    if (component && !isOwn()) {
                        checkMethodCall(match, component, call, call?.arguments ?? [], target);
                    }
                });
        }

        /** Runs the checks of template expressions on the mustaches of a `.html.twig` text node. */
        function checkTwigMustaches(textNode) {
            // `.vue` templates parse their mustaches, only `.html.twig` keeps them as text.
            if (filename.endsWith('.vue') || mustacheNames.length === 0 || !textNode.value.includes('{{')) {
                return;
            }

            const isCandidate = (code) => mustacheNames.some((name) => code.includes(name));

            parseMustaches(sourceCode, textNode, isCandidate).forEach((expression) => {
                walkExpression(expression, (node) => {
                    if (node.type === 'Identifier') {
                        checkTemplateIdentifier(node);
                    }

                    if (node.type === 'MemberExpression') {
                        checkMemberUsage(node, true);
                    }

                    if (node.type === 'Literal' || node.type === 'TemplateLiteral') {
                        checkStringLiteral(node, true);
                    }
                });
            });
        }

        function checkTemplateElement(element) {
            removedComponents
                .filter(({ usage }) => usage.inTemplates !== false && matchesElementName(element, usage.names))
                .forEach((match) => {
                    report(match, { node: element.startTag }, match.usage.message ?? defaultHeadline(element.rawName));
                });

            element.startTag.attributes.forEach((attribute) => {
                templateAttributes
                    .filter(({ usage }) => !usage.elements || matchesElementName(element, usage.elements))
                    .filter(({ usage }) => matchesTemplateAttribute(attribute, usage.names) && isInScope(usage, null))
                    .forEach((match) => {
                        const name = sourceCode.getText(attribute.key);

                        report(match, { node: attribute }, match.usage.message ?? defaultHeadline(`${element.rawName} ${name}`));
                    });
            });
        }

        /** A native override file `<component>.override.vue` of a removed component. */
        function checkOverrideFile(program) {
            const component = overrideFileComponent(filename);

            if (!component || isCoreFile) {
                return;
            }

            const templateBody = sourceCode.ast.templateBody;
            const target = templateBody ? { node: templateBody } : scriptTarget(program.body[0] ?? program);

            removedComponents
                .filter(({ usage }) => usage.names.includes(component))
                .forEach((match) => report(match, target, match.usage.message ?? defaultHeadline(component)));
        }

        function checkModuleRegister(node) {
            if (
                ![
                    'Module.register',
                    'Shopware.Module.register',
                ].includes(dottedPath(node.callee))
            ) {
                return;
            }

            const options = unwrapTypeScript(node.arguments[1]);
            const property = options?.type === 'ObjectExpression' ? options.properties.find((candidate) => {
                return candidate.type === 'Property' && propertyName(candidate) === 'snippets';
            }) : null;

            if (!property) {
                return;
            }

            moduleSnippets.forEach((match) => {
                const headline = match.usage.message ?? 'Module.register() no longer accepts the "snippets" property.';
                const { problems, fix } = analyzeModuleSnippets(context, property);

                if (problems.length > 0) {
                    const todo = `${headline} Before you remove it: ${problems.join('; ')}.`;

                    report(match, scriptTarget(property), [headline, ...problems].join('\n'), { comment: todo });

                    return;
                }

                report(match, scriptTarget(property), headline, { fix });
            });
        }

        const scriptVisitor = {
            Program(node) {
                if (removedComponents.length > 0) {
                    checkOverrideFile(node);
                }
            },

            MemberExpression(node) {
                checkMemberUsage(node, false);
                checkThisMember(node);
            },

            VariableDeclarator(node) {
                if (node.id.type !== 'ObjectPattern' || !node.init) {
                    return;
                }

                let init = unwrapTypeScript(node.init);

                if (init.type === 'AwaitExpression') {
                    init = unwrapTypeScript(init.argument);
                }

                if (init.type === 'ThisExpression') {
                    checkThisDestructuring(node);
                }

                node.id.properties
                    .filter((property) => property.type === 'Property')
                    .forEach((property) => {
                        const name = propertyName(property);

                        memberUsages
                            .filter(({ usage }) => name !== null && usage.properties.includes(name))
                            .filter(({ usage }) => usage.withoutArgument === undefined && matchesReceiver(usage, init, false))
                            .filter(({ usage }) => isInScope(usage, property))
                            .forEach((match) => report(match, scriptTarget(property), memberUsageHeadline(match.usage, name)));
                    });
            },

            ObjectExpression(node) {
                if (componentMembers.length > 0 && componentOptions(node) === node) {
                    checkOptionMembers(node);
                }
            },

            Property(node) {
                if (node.parent.type !== 'ObjectExpression') {
                    return;
                }

                const name = propertyName(node);

                if (name === 'inject' && !node.computed) {
                    checkInjectOption(node);
                }

                const base = path.basename(filename);

                objectKeys
                    .filter(({ usage }) => name !== null && usage.names.includes(name))
                    .filter(({ usage }) => objectKeyFiles.get(usage)?.test(base) ?? true)
                    .forEach((match) => report(match, scriptTarget(node), match.usage.message ?? defaultHeadline(name)));
            },

            CallExpression(node) {
                checkSuperCall(node);

                if (node.callee.type === 'Identifier' && node.callee.name === 'inject') {
                    checkInjectKey(staticString(node.arguments[0]), node);
                }

                if (node.callee.type === 'Identifier' && node.callee.name === 'require' && node.arguments.length === 1) {
                    checkImport(node, staticString(node.arguments[0]), destructuredNames(node));
                }

                const factoryCall = componentFactoryCall(node);

                if (factoryCall && factoryCall.method !== 'register' && !isCoreFile) {
                    removedComponents
                        .filter(({ usage }) => usage.names.includes(factoryCall.name))
                        .forEach((match) => {
                            report(match, scriptTarget(node.callee), match.usage.message ?? defaultHeadline(factoryCall.name));
                        });
                }

                if (moduleSnippets.length > 0) {
                    checkModuleRegister(node);
                }
            },

            ImportDeclaration: checkImportDeclaration,
            ExportNamedDeclaration: checkImportDeclaration,
            ExportAllDeclaration: checkImportDeclaration,

            ImportExpression(node) {
                checkImport(node, staticString(node.source), destructuredNames(node));
            },

            Literal(node) {
                checkStringLiteral(node, false);
            },

            TemplateLiteral(node) {
                checkStringLiteral(node, false);
            },
        };

        // Plain script files run without vue-eslint-parser, so only templates get the template visitor.
        const defineTemplateBodyVisitor = sourceCode.parserServices?.defineTemplateBodyVisitor;

        if (!defineTemplateBodyVisitor) {
            return scriptVisitor;
        }

        return defineTemplateBodyVisitor(
            {
                VElement: checkTemplateElement,
                Identifier: checkTemplateIdentifier,
                MemberExpression(node) {
                    checkMemberUsage(node, true);
                },
                Literal(node) {
                    checkStringLiteral(node, true);
                },
                TemplateLiteral(node) {
                    checkStringLiteral(node, true);
                },
                // `{{ … }}` of `.html.twig` templates, which vue-eslint-parser keeps as text.
                VText: checkTwigMustaches,
            },
            scriptVisitor,
        );
    },
});
