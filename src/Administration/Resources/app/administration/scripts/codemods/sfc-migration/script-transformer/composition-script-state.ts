import type { BindingName, ObjectLiteralExpression, SourceFile } from 'ts-morph';
import { Node, SyntaxKind } from 'ts-morph';
import {
    extractModuleLevelCode,
    getDirectThisPropertyName,
    getSnippetCallExpressions,
    getSnippetPropertyAccesses,
    getThisRefName,
} from './ast';
import { extractComputedProps } from './extract-computed';
import {
    extractEmitsDefinition,
    extractInheritAttrs,
    extractPropNamesFromText,
    extractPropsText,
} from './extract-component-options';
import { extractDataProps } from './extract-data';
import { extractInjectProps } from './extract-inject';
import { extractLifecycleHooks } from './extract-lifecycle';
import { extractMethodProps } from './extract-methods';
import { extractWatchProps } from './extract-watch';
import { isDefined, isSafeIdentifier, sanitizeTodoCommentText } from './helpers';
import {
    collectEmittedEventNames,
    collectThisRefNames,
    detectUsedComposables,
    hasDirectThisPropertyUsage,
} from './rewrite-this';
import type {
    CodeSnippet,
    ComponentRegistration,
    ComputedProp,
    DataProp,
    EmitsDefinition,
    InjectProp,
    LifecycleHook,
    MethodProp,
    RewriteContext,
    UsedComposables,
    WatchProp,
} from './types';

export interface CompositionScriptState {
    registration: ComponentRegistration;
    ctx: RewriteContext;
    propsText: string | null;
    emitsDefinition: EmitsDefinition;
    effectiveEmitsKeys: string[];
    inheritAttrs: boolean;
    componentNameValue?: string;
    moduleLevelCode: string;
    todoComments: string[];
    supportedInjectProps: InjectProp[];
    supportedDataProps: DataProp[];
    supportedComputedProps: ComputedProp[];
    supportedMethodProps: MethodProp[];
    supportedWatchProps: WatchProp[];
    unsupportedWatchEntries: string[];
    lifecycleHooks: LifecycleHook[];
    regularHooks: LifecycleHook[];
    usedComposables: UsedComposables;
    templateRefNames: string[];
    publicNames: string[];
    vueImports: string[];
    propNames: Set<string>;
    injectNames: Set<string>;
    manualMigrationReasons: string[];
    existingBindingNames: Set<string>;
}

export function collectCompositionScriptState(
    optionsObj: ObjectLiteralExpression,
    registration: ComponentRegistration,
    sourceFile: SourceFile,
): CompositionScriptState {
    const { injectProps, unsupportedEntries: unsupportedInjectEntries } = extractInjectProps(optionsObj);
    const { dataProps, unsupportedEntries: unsupportedDataEntries } = extractDataProps(optionsObj);
    const { computedProps, unsupportedEntries: unsupportedComputedEntries } = extractComputedProps(optionsObj);
    const { watchProps, unsupportedEntries: unsupportedWatchEntries } = extractWatchProps(optionsObj);
    const { methodProps, unsupportedEntries: unsupportedMethodEntries } = extractMethodProps(optionsObj);
    const lifecycleHooks = extractLifecycleHooks(optionsObj);
    const propsText = extractPropsText(optionsObj);
    const emitsDefinition = extractEmitsDefinition(optionsObj);
    const inheritAttrs = extractInheritAttrs(optionsObj);
    const moduleLevelCode = extractModuleLevelCode(sourceFile, registration);
    const manualMigrationReasons: string[] = [];
    const todoComments: string[] = [];
    const extractedMethodNames = new Set(methodProps.map(({ name }) => name));

    const supportedInjectProps = injectProps.filter(({ localName }) => {
        if (isSafeIdentifier(localName)) {
            return true;
        }

        const reason = `inject: ${localName} is not a valid JavaScript identifier`;
        manualMigrationReasons.push(reason);
        todoComments.push(`// TODO: migrate inject entry manually: ${sanitizeTodoCommentText(reason)}`);
        return false;
    });

    unsupportedInjectEntries.forEach((entry) => {
        const reason = `inject: ${sanitizeTodoCommentText(entry)}`;
        manualMigrationReasons.push(reason);
        todoComments.push(`// TODO: migrate inject entry manually: ${sanitizeTodoCommentText(reason)}`);
    });

    const supportedDataProps = dataProps.filter(({ name, valueText }) => {
        if (isSafeIdentifier(name)) {
            const calledMethodName = findDataInitializerMethodCall(valueText, extractedMethodNames);
            if (calledMethodName) {
                const reason = `data: ${name} initializer calls component method '${calledMethodName}'`;
                manualMigrationReasons.push(reason);
                todoComments.push(`// TODO: migrate data entry manually: ${sanitizeTodoCommentText(reason)}`);
                return false;
            }

            return true;
        }

        const reason = `data: ${name} is not a valid JavaScript identifier`;
        manualMigrationReasons.push(reason);
        todoComments.push(`// TODO: migrate data entry manually: ${sanitizeTodoCommentText(reason)}`);
        return false;
    });

    unsupportedDataEntries.forEach((entry) => {
        const reason = `data: ${sanitizeTodoCommentText(entry)}`;
        manualMigrationReasons.push(reason);
        todoComments.push(`// TODO: migrate data entry manually: ${sanitizeTodoCommentText(reason)}`);
    });

    const supportedComputedProps = computedProps.filter((prop) => {
        if (isSafeIdentifier(prop.name)) {
            return true;
        }

        const reason = `computed: ${prop.name} is not a valid JavaScript identifier`;
        manualMigrationReasons.push(reason);
        todoComments.push(`// TODO: migrate computed entry manually: ${sanitizeTodoCommentText(reason)}`);
        return false;
    });

    unsupportedComputedEntries.forEach((entry) => {
        const reason = `computed: ${sanitizeTodoCommentText(entry)}`;
        manualMigrationReasons.push(reason);
        todoComments.push(`// TODO: migrate computed entry manually: ${sanitizeTodoCommentText(reason)}`);
    });

    const injectNames = new Set(supportedInjectProps.map((p) => p.localName));
    const propNames = new Set(propsText ? extractPropNamesFromText(optionsObj) : []);
    const dataNames = new Set(supportedDataProps.map((p) => p.name));
    const computedNames = new Set(supportedComputedProps.map((p) => p.name));
    const extractableMethodNames = new Set(methodProps.filter(({ name }) => isSafeIdentifier(name)).map(({ name }) => name));
    const methodValidationCtx: RewriteContext = {
        propNames,
        dataNames,
        computedNames,
        methodNames: extractableMethodNames,
        injectNames,
    };

    const supportedMethodProps = methodProps.filter(({ name, bodyText, rawText }) => {
        if (isSafeIdentifier(name)) {
            const unsupportedThisName = findUnsupportedThisPropertyUsage(
                {
                    text: rawText ?? bodyText,
                    kind: rawText === undefined ? 'body' : 'expression',
                },
                methodValidationCtx,
            );

            if (unsupportedThisName) {
                const reason = `methods: ${name} uses unknown this property '${unsupportedThisName}'`;
                manualMigrationReasons.push(reason);
                todoComments.push(`// TODO: migrate method manually: ${sanitizeTodoCommentText(reason)}`);
                return false;
            }

            return true;
        }

        const reason = `methods: ${name} is not a valid JavaScript identifier`;
        manualMigrationReasons.push(reason);
        todoComments.push(`// TODO: migrate method manually: ${sanitizeTodoCommentText(reason)}`);
        return false;
    });

    unsupportedMethodEntries.forEach((entry) => {
        const reason = `methods: ${sanitizeTodoCommentText(entry)}`;
        manualMigrationReasons.push(reason);
        todoComments.push(`// TODO: migrate method manually: ${sanitizeTodoCommentText(reason)}`);
    });

    const methodNames = new Set(supportedMethodProps.map((p) => p.name));

    const ctx: RewriteContext = { propNames, dataNames, computedNames, methodNames, injectNames };

    // These snippets are the only source ranges that will be emitted into
    // setup. They drive import detection, template refs, inferred emits, and
    // `this.` rewriting without touching strings or comments elsewhere.
    const allSnippets: CodeSnippet[] = [
        ...supportedDataProps.map((p) => ({ text: p.valueText, kind: 'expression' as const })),
        ...supportedComputedProps.flatMap((p) =>
            p.kind === 'getter'
                ? [{ text: p.bodyText, kind: 'body' as const }]
                : [
                      { text: p.getterBodyText, kind: 'body' as const },
                      { text: p.setterBodyText, kind: 'body' as const },
                  ],
        ),
        ...watchProps.map((p) => (p.bodyText ? { text: p.bodyText, kind: 'body' as const } : undefined)),
        ...supportedMethodProps.map((p) => ({
            text: p.bodyText,
            kind: p.rawText === undefined ? ('body' as const) : ('expression' as const),
        })),
        ...lifecycleHooks.map((h) => ({ text: h.bodyText, kind: 'body' as const })),
    ].filter(isDefined);

    const usedComposables = detectUsedComposables(allSnippets, watchProps);
    const templateRefNames = collectThisRefNames(allSnippets);

    if (hasDirectThisPropertyUsage(allSnippets, '$store')) {
        manualMigrationReasons.push('$store usage requires manual migration to the appropriate Pinia store or composable');
    }

    const effectiveEmitsKeys =
        emitsDefinition.keys.length > 0 || emitsDefinition.objectText !== null
            ? emitsDefinition.keys
            // TODO: Silent ignore: unsupported emits definitions can be
            // replaced by inferred `$emit()` names instead of reporting the
            // original `emits` shape as unsupported.
            : collectEmittedEventNames(allSnippets);

    const supportedWatchProps = watchProps.filter((watchProp) => {
        if (watchProp.name.includes('.')) {
            unsupportedWatchEntries.push(`${watchProp.name}: nested watch paths are not supported`);
            return false;
        }

        // Vue 2 accepted string paths in watch definitions. In Composition API
        // we can only generate a safe source when that path maps to a prop,
        // data ref, computed ref, or inject declared by this codemod.
        const isKnownWatchTarget =
            propNames.has(watchProp.name) ||
            dataNames.has(watchProp.name) ||
            computedNames.has(watchProp.name) ||
            injectNames.has(watchProp.name);

        if (watchProp.name !== '$route' && !isKnownWatchTarget) {
            if (!isSafeIdentifier(watchProp.name)) {
                unsupportedWatchEntries.push(
                    `${watchProp.name}: watch targets that are not valid identifiers must be migrated manually`,
                );
            } else {
                unsupportedWatchEntries.push(
                    `${watchProp.name}: watch target is not declared in props, data, computed, or inject`,
                );
            }

            return false;
        }

        if (watchProp.handlerName && !methodNames.has(watchProp.handlerName)) {
            unsupportedWatchEntries.push(
                `${watchProp.name}: string handler '${watchProp.handlerName}' was not found in methods`,
            );
            return false;
        }

        return true;
    });

    const vueImports: string[] = [];
    if (supportedDataProps.length > 0 || templateRefNames.length > 0) vueImports.push('ref');
    if (supportedComputedProps.length > 0) vueImports.push('computed');
    if (supportedInjectProps.length > 0) vueImports.push('inject');
    if (supportedWatchProps.length > 0) vueImports.push('watch');
    if (supportedWatchProps.some(({ name }) => injectNames.has(name))) vueImports.push('unref');
    if (usedComposables.needsNextTick) vueImports.push('nextTick');
    if (usedComposables.needsSlots) vueImports.push('useSlots');
    if (usedComposables.needsAttrs) vueImports.push('useAttrs');
    if (hasDirectThisPropertyUsage(allSnippets, '$el')) vueImports.push('getCurrentInstance');

    const regularHooks = lifecycleHooks.filter((h) => h.compositionName !== null);
    vueImports.push(...new Set(regularHooks.map((h) => h.compositionName as string)));

    const publicNames = [
        ...supportedInjectProps.map((p) => p.localName),
        ...supportedDataProps.map((p) => p.name),
        ...supportedComputedProps.map((p) => p.name),
        ...supportedMethodProps.map((p) => p.name),
    ];

    // These options can affect runtime registration or lifecycle order. The
    // generated setup code is still useful, but a successful-looking migration
    // would be misleading without explicit manual follow-up markers.
    if (optionsObj.getProperty('provide')) {
        manualMigrationReasons.push('provide option requires manual migration');
        todoComments.push('// TODO: migrate `provide` manually — map each key to provide(key, value) calls');
    }
    if (optionsObj.getProperty('components')) {
        manualMigrationReasons.push('components option requires manual verification');
        todoComments.push('// TODO: verify local component registrations in `components:` — remove if globally registered');
    }
    if (optionsObj.getProperty('directives')) {
        manualMigrationReasons.push('directives option requires manual migration');
        todoComments.push('// TODO: migrate `directives` manually');
    }
    if (optionsObj.getProperty('beforeCreate')) {
        manualMigrationReasons.push('beforeCreate hook requires manual migration');
        todoComments.push('// TODO: `beforeCreate` was dropped — move logic to top of setup if needed');
    }
    // TODO: Silent ignore: other runtime-relevant top-level options
    // (route guards, metaInfo, shortcuts, errorCaptured, expose,
    // extensionApiDevtoolInformation, saveFinish, root spreads, and dynamic
    // option keys) are not surfaced as manual migration reasons.

    manualMigrationReasons.push(...unsupportedWatchEntries.map((entry) => `watch: ${sanitizeTodoCommentText(entry)}`));

    const componentNameProp = optionsObj.getProperty('name');
    // TODO: Silent ignore: dynamic component `name` options are passed to
    // defineOptions instead of being reported as unsupported.
    const componentNameValue = componentNameProp?.isKind(SyntaxKind.PropertyAssignment)
        ? componentNameProp.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer()?.getText()
        : undefined;

    // TODO: Silent ignore: duplicate public names across inject/data/computed/
    // methods are not detected before generating duplicate setup declarations.

    return {
        registration,
        ctx,
        propsText,
        emitsDefinition,
        effectiveEmitsKeys,
        inheritAttrs,
        componentNameValue,
        moduleLevelCode,
        todoComments,
        supportedInjectProps,
        supportedDataProps,
        supportedComputedProps,
        supportedMethodProps,
        supportedWatchProps,
        unsupportedWatchEntries,
        lifecycleHooks,
        regularHooks,
        usedComposables,
        templateRefNames,
        publicNames,
        vueImports,
        propNames,
        injectNames,
        manualMigrationReasons,
        existingBindingNames: collectExistingBindingNames(sourceFile),
    };
}

function collectExistingBindingNames(sourceFile: SourceFile): Set<string> {
    const names = new Set<string>();

    for (const importDeclaration of sourceFile.getImportDeclarations()) {
        const defaultImport = importDeclaration.getDefaultImport();
        const namespaceImport = importDeclaration.getNamespaceImport();

        if (defaultImport) {
            names.add(defaultImport.getText());
        }

        if (namespaceImport) {
            names.add(namespaceImport.getText());
        }

        importDeclaration.getNamedImports().forEach((namedImport) => {
            names.add(namedImport.getAliasNode()?.getText() ?? namedImport.getName());
        });
    }

    sourceFile.getDescendants().forEach((node) => {
        if (Node.isVariableDeclaration(node)) {
            collectBindingName(node.getNameNode(), names);
        } else if (Node.isParameterDeclaration(node)) {
            collectBindingName(node.getNameNode(), names);
        } else if (Node.isBindingElement(node)) {
            collectBindingName(node.getNameNode(), names);
        } else if (Node.isFunctionDeclaration(node) || Node.isClassDeclaration(node)) {
            const name = node.getName();

            if (name) {
                names.add(name);
            }
        } else if (Node.isCatchClause(node)) {
            const variableDeclaration = node.getVariableDeclaration();

            if (variableDeclaration) {
                collectBindingName(variableDeclaration.getNameNode(), names);
            }
        }
    });

    return names;
}

function findDataInitializerMethodCall(valueText: string, methodNames: Set<string>): string | null {
    for (const callExpression of getSnippetCallExpressions({ text: valueText, kind: 'expression' })) {
        const expression = callExpression.getExpression();

        if (!Node.isPropertyAccessExpression(expression)) {
            continue;
        }

        const thisPropertyName = getDirectThisPropertyName(expression);
        if (thisPropertyName && methodNames.has(thisPropertyName)) {
            return thisPropertyName;
        }
    }

    return null;
}

function findUnsupportedThisPropertyUsage(snippet: CodeSnippet, ctx: RewriteContext): string | null {
    for (const propertyAccess of getSnippetPropertyAccesses(snippet)) {
        if (getThisRefName(propertyAccess)) {
            continue;
        }

        const thisPropertyName = getDirectThisPropertyName(propertyAccess);

        if (!thisPropertyName || isSupportedThisPropertyName(thisPropertyName, ctx)) {
            continue;
        }

        return thisPropertyName;
    }

    return null;
}

function isSupportedThisPropertyName(name: string, ctx: RewriteContext): boolean {
    return (
        name === '$emit' ||
        name === '$router' ||
        name === '$route' ||
        name === '$nextTick' ||
        name === '$slots' ||
        name === '$props' ||
        name === '$attrs' ||
        name === '$tc' ||
        name === '$t' ||
        name === '$refs' ||
        name === '$el' ||
        name === '$store' ||
        name === '$parent' ||
        name === '$root' ||
        name === '$options' ||
        name === '$forceUpdate' ||
        ctx.propNames.has(name) ||
        ctx.dataNames.has(name) ||
        ctx.computedNames.has(name) ||
        ctx.methodNames.has(name) ||
        ctx.injectNames.has(name)
    );
}

function collectBindingName(nameNode: BindingName, names: Set<string>): void {
    if (Node.isIdentifier(nameNode)) {
        names.add(nameNode.getText());
        return;
    }

    nameNode.getElements().forEach((element) => {
        collectBindingName(element.getNameNode(), names);
    });
}
