/**
 * @sw-package framework
 *
 * Builds the ESLint rule of one entry of `data/v6.8/api-usage.json`: one rule per removed or changed
 * Administration API. The rule finds the usages of the entry in scripts (JavaScript, TypeScript and
 * the script of `.vue` files) and in templates (`.vue` templates and `.html.twig` through the
 * twig-vue processor), see `data/v6.8/README.md` for the usage types.
 *
 * `replace-component-member`, `replace-component-method-call` and `module-snippets` fix the code;
 * every other usage is reported, and at the `partial` and `manual` run levels gets a review comment
 * with its message, see `registry/run-level.js`.
 *
 * Usages with `components` only match in overrides and extensions of those components:
 * - script spots inside `Component.override()` or `Component.extend()` of one of them, or in its
 *   native override `<component>.override.vue`. `Component.register()` defines a component of its
 *   own, so its members never count.
 * - template spots in such an `.override.vue`, or in a `.html.twig` that belongs to one of them,
 *   see `fileComponents()` in `registry/component-scope.js`.
 */

const path = require('path');
const { FIX_OPTION_SCHEMA, canAutoFix } = require('../registry/fix-policy');
const { withRunLevel } = require('../registry/run-level');
const { analyzeModuleSnippets } = require('../registry/module-snippets');
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
} = require('../registry/component-scope');
const {
    expressionContainerOf,
    isTemplateLocal,
    isTemplateReference,
    matchesElementName,
    matchesTemplateAttribute,
    parseMustaches,
    walkExpression,
} = require('../registry/template-expressions');
const {
    argumentSource,
    asExpression,
    callOf,
    destructuredNames,
    importedNames,
    injectedKey,
    isComparedStrictlyWithNullish,
    isStringValue,
    isWritten,
    leavesOutArgument,
    matchesImportSource,
} = require('./api-usage-nodes');
const { entryMessage } = require('./entry-message');

/**
 * The usage types of `api-usage.json` with the fix level a usage has when it does not name one:
 * `auto` fixes keep the behaviour, `manual` usages are only reported.
 */
const DEFAULT_FIX_LEVELS = {
    'member-usage': 'manual',
    'component-member': 'manual',
    'replace-component-member': 'auto',
    'replace-component-method-call': 'auto',
    'inject-key': 'manual',
    'removed-component': 'manual',
    'import-source': 'manual',
    'string-literal': 'manual',
    'object-key': 'manual',
    'template-attribute': 'manual',
    'module-snippets': 'auto',
};

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

// Receivers that reach the same global object, e.g. `window.Shopware.State`.
const GLOBAL_OBJECTS = /^(?:window|globalThis|self)\./;

/**
 * The usages of an entry with their fix level. Throws for a usage type the factory does not know, so
 * a typo in the data fails the build instead of silently matching nothing.
 *
 * @example
 * normalizeUsages({ id: 'api.x', usage: [{ type: 'inject-key', names: ['a'] }] });
 * // [{ type: 'inject-key', names: ['a'], fix: 'manual' }]
 */
function normalizeUsages(entry) {
    return entry.usage.map((usage) => {
        if (!(usage.type in DEFAULT_FIX_LEVELS)) {
            throw new Error(`Unknown usage type "${usage.type}" in "${entry.id}".`);
        }

        return { ...usage, fix: usage.fix ?? DEFAULT_FIX_LEVELS[usage.type] };
    });
}

function defaultHeadline(api, replacement = null) {
    return `The "${api}" API is deprecated.${replacement ? ` Use ${replacement} instead.` : ''}`;
}

function memberUsageHeadline(usage, name) {
    const receiver = usage.object ?? usage.service ?? (usage.store ? `Shopware.Store.get('${usage.store}')` : null);

    return usage.message ?? defaultHeadline(receiver ? `${receiver}.${name}` : name);
}

/**
 * The visitors of one rule. `usages` are the normalized usages of its entry.
 */
function createVisitors(context, entry, usages) {
    const sourceCode = context.sourceCode;
    const filename = context.physicalFilename ?? context.filename;
    // The Administration's own files define the components, so they hold no overrides of removed ones.
    const isCoreFile = isAdministrationSource(filename);
    const of = (type) => usages.filter((usage) => usage.type === type);
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
    const objectKeyFiles = new Map(objectKeys.map((usage) => [usage, usage.files ? new RegExp(usage.files) : null]));
    const reported = new Set();
    const thisMemberNames = new Set([
        ...componentMembers.flatMap((usage) => usage.names),
        ...memberReplacements.map((usage) => usage.name),
        ...methodCallReplacements.map((usage) => usage.name),
    ]);
    // Names a `.html.twig` mustache has to contain to be worth parsing.
    const mustacheNames = [
        ...memberUsages.flatMap((usage) => usage.properties),
        ...thisMemberNames,
        ...stringLiterals.flatMap((usage) => usage.values),
    ];
    let templateComponents = null;

    /**
     * Reports a spot once per message. With a fix, the usage's fix level applies; without one, or
     * when the usage may not be fixed, the spot is manual work and the run level writes the review
     * comment (`comment`, by default the usage's message, otherwise the headline).
     */
    function report(usage, target, headline, { fix = null, comment = null } = {}) {
        const message = entryMessage([headline], entry);
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
     * line, so a spot whose line starts inside a multi-line string or template literal is reported
     * from the first line that starts with code.
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

    /** The first component of a usage the spot belongs to; null when it belongs to none. */
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

    /** For `withoutArgument`: whether the call of `node` leaves that argument out. */
    function matchesArguments(usage, node) {
        return usage.withoutArgument === undefined || leavesOutArgument(node, usage.withoutArgument);
    }

    function checkMemberUsage(node, isTemplate) {
        const name = memberUsages.length > 0 ? propertyName(node) : null;

        if (name === null) {
            return;
        }

        memberUsages
            .filter((usage) => usage.properties.includes(name))
            .filter((usage) => matchesReceiver(usage, node.object, isTemplate))
            .filter((usage) => isInScope(usage, isTemplate ? null : node) && matchesArguments(usage, node))
            .forEach((usage) => {
                report(usage, isTemplate ? templateTarget(node) : scriptTarget(node), memberUsageHeadline(usage, name));
            });
    }

    function checkDestructuredMembers(declarator, init) {
        if (memberUsages.length === 0) {
            return;
        }

        declarator.id.properties
            .filter((property) => property.type === 'Property')
            .forEach((property) => {
                const name = propertyName(property);

                memberUsages
                    .filter((usage) => name !== null && usage.properties.includes(name))
                    .filter((usage) => usage.withoutArgument === undefined && matchesReceiver(usage, init, false))
                    .filter((usage) => isInScope(usage, property))
                    .forEach((usage) => report(usage, scriptTarget(property), memberUsageHeadline(usage, name)));
            });
    }

    // replace-component-method-call

    /**
     * Rewrites a call of a removed method, or reports it when it cannot: other arguments than
     * `with` takes, an optional call, or a strict comparison with null when the usage says so.
     */
    function checkMethodCall(usage, component, call, args, target) {
        const headline = usage.message ?? defaultHeadline(`${component}.${usage.name}()`, usage.with.replace('{0}', '…'));
        const needsArgument = usage.with.includes('{0}');
        const isRewritable =
            call &&
            !call.optional &&
            args.length === (needsArgument ? 1 : 0) &&
            args.every((argument) => argument.type !== 'SpreadElement');

        if (!isRewritable) {
            report(usage, target, headline);

            return;
        }

        if (usage.strictNullMessage && isComparedStrictlyWithNullish(call)) {
            report(usage, target, usage.strictNullMessage, { comment: usage.strictNullMessage });

            return;
        }

        const argument = args[0] ? argumentSource(sourceCode, args[0]) : '';
        const replacement = asExpression(usage.with.replaceAll('{0}', argument));

        report(usage, target, headline, { fix: (fixer) => fixer.replaceTextRange(call.range, replacement) });
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
            .filter((usage) => usage.names.includes(name))
            .forEach((usage) => {
                const component = matchedComponent(usage, node);

                if (component) {
                    report(usage, scriptTarget(node), usage.message ?? defaultHeadline(`${component}.${name}`));
                }
            });

        memberReplacements
            .filter((usage) => usage.name === name)
            .forEach((usage) => {
                const component = matchedComponent(usage, node);

                if (!component) {
                    return;
                }

                const headline = usage.message ?? defaultHeadline(`${component}.${name}`, usage.with);

                if (isWritten(node)) {
                    report(usage, scriptTarget(node), headline);

                    return;
                }

                report(usage, scriptTarget(node), headline, {
                    fix: (fixer) => fixer.replaceTextRange(node.range, asExpression(usage.with)),
                });
            });

        methodCallReplacements
            .filter((usage) => usage.name === name)
            .forEach((usage) => {
                const component = matchedComponent(usage, node);
                const call = callOf(node);

                if (component) {
                    checkMethodCall(usage, component, call, call?.arguments ?? [], scriptTarget(node));
                }
            });
    }

    /** `this.$super('<name>', …)`: the component no longer has the member, also when the override defines it. */
    function checkSuperCall(node) {
        const callee = node.callee;

        if (
            thisMemberNames.size === 0 ||
            callee.type !== 'MemberExpression' ||
            unwrapTypeScript(callee.object).type !== 'ThisExpression' ||
            propertyName(callee) !== '$super'
        ) {
            return;
        }

        const name = staticString(node.arguments[0]);

        componentMembers
            .filter((usage) => usage.names.includes(name))
            .forEach((usage) => {
                const component = matchedComponent(usage, node);

                if (component) {
                    report(usage, scriptTarget(node), usage.message ?? defaultHeadline(`${component}.${name}`));
                }
            });

        memberReplacements
            .filter((usage) => usage.name === name)
            .forEach((usage) => {
                const component = matchedComponent(usage, node);

                if (component) {
                    report(usage, scriptTarget(node), usage.message ?? defaultHeadline(`${component}.${name}`, usage.with), {
                        fix: (fixer) => fixer.replaceTextRange(node.range, asExpression(usage.with)),
                    });
                }
            });

        methodCallReplacements
            .filter((usage) => usage.name === name)
            .forEach((usage) => {
                const component = matchedComponent(usage, node);

                if (component) {
                    checkMethodCall(usage, component, node, node.arguments.slice(1), scriptTarget(node));
                }
            });
    }

    /** `const { <name> } = this` in an override: a member the component lost. */
    function checkThisDestructuring(declarator) {
        if (thisMemberNames.size === 0) {
            return;
        }

        const options = componentOptions(declarator);

        declarator.id.properties
            .filter((property) => property.type === 'Property')
            .forEach((property) => {
                const name = propertyName(property);

                if (!thisMemberNames.has(name) || definesMember(options, OWN_MEMBER_SECTIONS, name)) {
                    return;
                }

                [
                    ...componentMembers.filter((usage) => usage.names.includes(name)),
                    ...memberReplacements.filter((usage) => usage.name === name),
                    ...methodCallReplacements.filter((usage) => usage.name === name),
                ].forEach((usage) => {
                    const component = matchedComponent(usage, property);

                    if (component) {
                        report(usage, scriptTarget(property), usage.message ?? defaultHeadline(`${component}.${name}`));
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
                    .filter((usage) => usage.names.includes(member.name))
                    .forEach((usage) => {
                        const component = matchedComponent(usage, options);

                        if (component) {
                            report(usage, scriptTarget(member.node), usage.message ?? defaultHeadline(`${component}.${member.name}`));
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
            .filter((usage) => usage.names.includes(key) && isInScope(usage, node))
            .forEach((usage) => report(usage, scriptTarget(node), usage.message ?? defaultHeadline(`inject('${key}')`)));
    }

    function checkInjectOption(property) {
        const value = unwrapTypeScript(property.value);

        if (value?.type === 'ArrayExpression') {
            value.elements.filter(Boolean).forEach((element) => checkInjectKey(staticString(element), element));
        }

        if (value?.type === 'ObjectExpression') {
            value.properties
                .filter((candidate) => candidate.type === 'Property')
                .forEach((candidate) => checkInjectKey(injectedKey(candidate), candidate));
        }
    }

    // import-source

    function checkImport(node, source, names) {
        importSources
            .filter((usage) => matchesImportSource(source, usage.sources))
            .filter((usage) => !usage.specifiers || (names ?? []).some((name) => usage.specifiers.includes(name)))
            .forEach((usage) => report(usage, scriptTarget(node), usage.message ?? defaultHeadline(source)));
    }

    function checkImportDeclaration(node) {
        const names = node.source && importSources.length > 0 ? importedNames(node) : null;

        if (names !== null) {
            checkImport(node, node.source.value, node.type === 'ExportAllDeclaration' ? null : names);
        }
    }

    // string-literal

    function checkStringLiteral(node, isTemplate) {
        const value = stringLiterals.length > 0 ? staticString(node) : null;

        if (value === null || !isStringValue(node)) {
            return;
        }

        stringLiterals
            .filter((usage) => usage.values.includes(value) && isInScope(usage, isTemplate ? null : node))
            .forEach((usage) => {
                report(usage, isTemplate ? templateTarget(node) : scriptTarget(node), usage.message ?? defaultHeadline(value));
            });
    }

    // object-key

    function checkObjectKey(node, name) {
        const base = path.basename(filename);

        objectKeys
            .filter((usage) => name !== null && usage.names.includes(name))
            .filter((usage) => objectKeyFiles.get(usage)?.test(base) ?? true)
            .forEach((usage) => report(usage, scriptTarget(node), usage.message ?? defaultHeadline(name)));
    }

    // removed-component

    function checkFactoryCall(node) {
        const factoryCall = removedComponents.length > 0 && !isCoreFile ? componentFactoryCall(node) : null;

        if (!factoryCall || factoryCall.method === 'register') {
            return;
        }

        removedComponents
            .filter((usage) => usage.names.includes(factoryCall.name))
            .forEach((usage) => report(usage, scriptTarget(node.callee), usage.message ?? defaultHeadline(factoryCall.name)));
    }

    /** A native override file `<component>.override.vue` of a removed component. */
    function checkOverrideFile(program) {
        const component = removedComponents.length > 0 && !isCoreFile ? overrideFileComponent(filename) : null;

        if (!component) {
            return;
        }

        const templateBody = sourceCode.ast.templateBody;
        const target = templateBody ? { node: templateBody } : scriptTarget(program.body[0] ?? program);

        removedComponents
            .filter((usage) => usage.names.includes(component))
            .forEach((usage) => report(usage, target, usage.message ?? defaultHeadline(component)));
    }

    // module-snippets

    function checkModuleRegister(node) {
        if (
            moduleSnippets.length === 0 ||
            ![
                'Module.register',
                'Shopware.Module.register',
            ].includes(dottedPath(node.callee))
        ) {
            return;
        }

        const options = unwrapTypeScript(node.arguments[1]);
        const property =
            options?.type === 'ObjectExpression'
                ? options.properties.find((candidate) => candidate.type === 'Property' && propertyName(candidate) === 'snippets')
                : null;

        if (!property) {
            return;
        }

        moduleSnippets.forEach((usage) => {
            const headline = usage.message ?? 'Module.register() no longer accepts the "snippets" property.';
            const { problems, fix } = analyzeModuleSnippets(context, property);

            if (problems.length > 0) {
                const comment = `${headline} Before you remove it: ${problems.join('; ')}.`;

                report(usage, scriptTarget(property), [headline, ...problems].join('\n'), { comment });

                return;
            }

            report(usage, scriptTarget(property), headline, { fix });
        });
    }

    // Template spots of component members: identifiers in template expressions.

    function checkTemplateIdentifier(node) {
        if (thisMemberNames.size === 0 && memberUsages.length === 0) {
            return;
        }

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
            .filter((usage) => usage.object === 'this' && usage.properties.includes(name))
            .filter((usage) => isInScope(usage, null) && matchesArguments(usage, node) && !isOwn())
            .forEach((usage) => report(usage, target, memberUsageHeadline(usage, name)));

        componentMembers
            .filter((usage) => usage.names.includes(name))
            .forEach((usage) => {
                const component = matchedComponent(usage, null);

                if (component && !isOwn()) {
                    report(usage, target, usage.message ?? defaultHeadline(`${component}.${name}`));
                }
            });

        memberReplacements
            .filter((usage) => usage.name === name)
            .forEach((usage) => {
                if (matchedComponent(usage, null) && !isOwn()) {
                    report(usage, target, usage.templateMessage, { comment: usage.templateMessage });
                }
            });

        methodCallReplacements
            .filter((usage) => usage.name === name)
            .forEach((usage) => {
                const component = matchedComponent(usage, null);
                const call = node.parent?.type === 'CallExpression' && node.parent.callee === node ? node.parent : null;

                if (component && !isOwn()) {
                    checkMethodCall(usage, component, call, call?.arguments ?? [], target);
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
            .filter((usage) => usage.inTemplates !== false && matchesElementName(element, usage.names))
            .forEach((usage) => report(usage, { node: element.startTag }, usage.message ?? defaultHeadline(element.rawName)));

        if (templateAttributes.length === 0) {
            return;
        }

        element.startTag.attributes.forEach((attribute) => {
            templateAttributes
                .filter((usage) => !usage.elements || matchesElementName(element, usage.elements))
                .filter((usage) => matchesTemplateAttribute(attribute, usage.names) && isInScope(usage, null))
                .forEach((usage) => {
                    const name = sourceCode.getText(attribute.key);

                    report(usage, { node: attribute }, usage.message ?? defaultHeadline(`${element.rawName} ${name}`));
                });
        });
    }

    const scriptVisitor = {
        Program: checkOverrideFile,

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

            checkDestructuredMembers(node, init);
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

            if (name === 'inject' && !node.computed && injectKeys.length > 0) {
                checkInjectOption(node);
            }

            checkObjectKey(node, name);
        },

        CallExpression(node) {
            checkSuperCall(node);

            if (node.callee.type === 'Identifier' && node.callee.name === 'inject') {
                checkInjectKey(staticString(node.arguments[0]), node);
            }

            if (node.callee.type === 'Identifier' && node.callee.name === 'require' && node.arguments.length === 1) {
                checkImport(node, staticString(node.arguments[0]), destructuredNames(node));
            }

            checkFactoryCall(node);
            checkModuleRegister(node);
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
}

/**
 * Creates the rule of one `api-usage.json` entry. The rule reports every usage of the entry, fixes
 * the ones its type can fix, and follows the run level of the ESLint settings.
 *
 * @example
 * const rule = createApiUsageRule(require('../data/v6.8/api-usage.json')[0]);
 *
 * @type {(entry: { id: string, description: string, removedIn: string, upgrade?: string[], usage: object[] }) => import('eslint').Rule.RuleModule}
 */
function createApiUsageRule(entry) {
    const usages = normalizeUsages(entry);

    return withRunLevel({
        meta: {
            type: 'problem',
            docs: {
                description: entry.description,
                recommended: false,
            },
            fixable: 'code',
            schema: FIX_OPTION_SCHEMA,
        },

        create(context) {
            return createVisitors(context, entry, usages);
        },
    });
}

module.exports = {
    createApiUsageRule,
};
