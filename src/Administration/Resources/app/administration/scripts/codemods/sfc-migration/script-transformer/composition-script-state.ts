import type { BindingName, ObjectLiteralExpression, SourceFile } from 'ts-morph';
import { Node, SyntaxKind } from 'ts-morph';
import { collectModuleLocalNames, extractModuleLevelCode } from './ast';
import { extractComputedProps } from './extract-computed';
import {
    analyzeEmitsShape,
    analyzePropsShape,
    extractEmitsDefinition,
    extractInheritAttrs,
    extractPropNamesFromText,
    extractPropsText,
} from './extract-component-options';
import { extractDataProps } from './extract-data';
import { extractInjectProps } from './extract-inject';
import { analyzeUnsupportedLifecycleHooks, extractLifecycleHooks } from './extract-lifecycle';
import { extractMethodProps } from './extract-methods';
import { extractWatchProps } from './extract-watch';
import { isDefined, isSafeIdentifier, sanitizeTodoCommentText } from './helpers';
import {
    collectEmittedEventNames,
    collectThisRefNames,
    detectUsedComposables,
    findDataInitializerMethodCall,
    findUnsupportedThisUsage,
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

interface SupportedCompositionMembers {
    supportedInjectProps: InjectProp[];
    supportedDataProps: DataProp[];
    supportedComputedProps: ComputedProp[];
    supportedMethodProps: MethodProp[];
    supportedWatchProps: WatchProp[];
    watchProps: WatchProp[];
    unsupportedWatchEntries: string[];
    propNames: Set<string>;
    dataNames: Set<string>;
    computedNames: Set<string>;
    methodNames: Set<string>;
    injectNames: Set<string>;
    manualMigrationReasons: string[];
    todoComments: string[];
}

interface ManualMigrationCollection {
    manualMigrationReasons: string[];
    todoComments: string[];
}

export function collectCompositionScriptState(
    optionsObj: ObjectLiteralExpression,
    registration: ComponentRegistration,
    sourceFile: SourceFile,
): CompositionScriptState {
    const lifecycleHooks = extractLifecycleHooks(optionsObj);
    const inheritAttrs = extractInheritAttrs(optionsObj);
    const moduleLevelCode = extractModuleLevelCode(sourceFile, registration);
    const moduleLocalNames = collectModuleLocalNames(sourceFile, registration);

    const manualMigrationReasons: string[] = [];
    const todoComments: string[] = [];

    // Unsupported props/emits shapes are suppressed to empty compiler macros so
    // no non-equivalent defineProps/defineEmits leaks; methods that depended on
    // the dropped members then surface as unresolved `this.<name>` follow-ups.
    const propsIssue = analyzePropsShape(optionsObj, moduleLocalNames);
    const propsText = propsIssue && !propsIssue.backoff ? null : extractPropsText(optionsObj);
    if (propsIssue && !propsIssue.backoff) {
        pushManualMigration(manualMigrationReasons, todoComments, 'props', propsIssue.reason);
    }

    const emitsIssue = analyzeEmitsShape(optionsObj, moduleLocalNames);
    const emitsDefinition =
        emitsIssue && !emitsIssue.backoff ? { keys: [], objectText: null } : extractEmitsDefinition(optionsObj);
    if (emitsIssue && !emitsIssue.backoff) {
        pushManualMigration(manualMigrationReasons, todoComments, 'emits', emitsIssue.reason);
    }

    const supportedMembers = collectSupportedCompositionMembers(optionsObj, propsText);
    manualMigrationReasons.push(...supportedMembers.manualMigrationReasons);
    todoComments.push(...supportedMembers.todoComments);

    const {
        supportedInjectProps,
        supportedDataProps,
        supportedComputedProps,
        supportedMethodProps,
        supportedWatchProps,
        unsupportedWatchEntries,
        propNames,
        dataNames,
        computedNames,
        methodNames,
        injectNames,
    } = supportedMembers;

    const allSnippets = collectSetupSnippets(supportedMembers, lifecycleHooks);

    const usedComposables = detectUsedComposables(allSnippets, supportedMembers.watchProps);
    const templateRefNames = collectThisRefNames(allSnippets);

    if (hasDirectThisPropertyUsage(allSnippets, '$store')) {
        manualMigrationReasons.push('$store usage requires manual migration to the appropriate Pinia store or composable');
    }
    collectPlaceholderApiReasons(allSnippets, manualMigrationReasons, todoComments);

    const effectiveEmitsKeys =
        emitsDefinition.keys.length > 0 || emitsDefinition.objectText !== null
            ? emitsDefinition.keys
            : collectEmittedEventNames(allSnippets);

    const regularHooks = lifecycleHooks.filter((h) => h.compositionName !== null);
    const vueImports = collectVueImports(supportedMembers, templateRefNames, usedComposables, regularHooks, allSnippets);
    const publicNames = collectPublicNames(supportedMembers);
    const manualFollowUps = collectManualFollowUps(optionsObj);

    manualMigrationReasons.push(...manualFollowUps.manualMigrationReasons);
    todoComments.push(...manualFollowUps.todoComments);

    manualMigrationReasons.push(...unsupportedWatchEntries.map((entry) => `watch: ${sanitizeTodoCommentText(entry)}`));

    const { componentNameValue, isDynamic: hasDynamicName } = resolveComponentNameValue(optionsObj);
    if (hasDynamicName) {
        pushManualMigration(
            manualMigrationReasons,
            todoComments,
            'name',
            'name: dynamic component name option must be migrated manually',
        );
    }
    if (hasDynamicInheritAttrs(optionsObj)) {
        pushManualMigration(
            manualMigrationReasons,
            todoComments,
            'inheritAttrs',
            'inheritAttrs: dynamic inheritAttrs expression must be migrated manually',
        );
    }

    const ctx: RewriteContext = { propNames, dataNames, computedNames, methodNames, injectNames };

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

function collectSupportedCompositionMembers(
    optionsObj: ObjectLiteralExpression,
    propsText: string | null,
): SupportedCompositionMembers {
    const { injectProps, unsupportedEntries: unsupportedInjectEntries } = extractInjectProps(optionsObj);
    const { dataProps, unsupportedEntries: unsupportedDataEntries } = extractDataProps(optionsObj);
    const { computedProps, unsupportedEntries: unsupportedComputedEntries } = extractComputedProps(optionsObj);
    const { watchProps, unsupportedEntries } = extractWatchProps(optionsObj);
    const unsupportedWatchEntries = [...unsupportedEntries];
    const { methodProps, unsupportedEntries: unsupportedMethodEntries } = extractMethodProps(optionsObj);
    const manualMigrationReasons: string[] = [];
    const todoComments: string[] = [];

    const supportedInjectProps = collectSupportedNamedProps(
        injectProps,
        ({ localName }) => localName,
        'inject',
        'inject entry',
        manualMigrationReasons,
        todoComments,
    );
    collectUnsupportedEntries(unsupportedInjectEntries, 'inject', 'inject entry', manualMigrationReasons, todoComments);

    const identifierSafeDataProps = collectSupportedNamedProps(
        dataProps,
        ({ name }) => name,
        'data',
        'data entry',
        manualMigrationReasons,
        todoComments,
    );
    collectUnsupportedEntries(unsupportedDataEntries, 'data', 'data entry', manualMigrationReasons, todoComments);

    const supportedComputedProps = collectSupportedNamedProps(
        computedProps,
        ({ name }) => name,
        'computed',
        'computed entry',
        manualMigrationReasons,
        todoComments,
    );
    collectUnsupportedEntries(
        unsupportedComputedEntries,
        'computed',
        'computed entry',
        manualMigrationReasons,
        todoComments,
    );

    const identifierSafeMethodProps = collectSupportedNamedProps(
        methodProps,
        ({ name }) => name,
        'methods',
        'method',
        manualMigrationReasons,
        todoComments,
    );
    collectUnsupportedEntries(unsupportedMethodEntries, 'methods', 'method', manualMigrationReasons, todoComments);

    const propNames = new Set(propsText ? extractPropNamesFromText(optionsObj) : []);

    // Dropping a member can turn a `this.<member>` reference inside another
    // member into an unresolved access, and removing duplicates can do the same.
    // Iterate to a fixpoint so the emitted setup never keeps a stale instance
    // reference to a member that was filtered out.
    let members: SupportedPublicMembers = {
        supportedInjectProps,
        supportedDataProps: identifierSafeDataProps,
        supportedComputedProps,
        supportedMethodProps: identifierSafeMethodProps,
    };

    for (;;) {
        const ctx = buildMemberContext(members, propNames);
        const filteredData = filterInstanceDependentData(
            members.supportedDataProps,
            ctx,
            manualMigrationReasons,
            todoComments,
        );
        const filteredMethods = filterInstanceDependentMethods(
            members.supportedMethodProps,
            ctx,
            manualMigrationReasons,
            todoComments,
        );
        const deduped = dropDuplicatePublicNames(
            { ...members, supportedDataProps: filteredData, supportedMethodProps: filteredMethods },
            manualMigrationReasons,
            todoComments,
        );

        const settled =
            deduped.supportedInjectProps.length === members.supportedInjectProps.length &&
            deduped.supportedDataProps.length === members.supportedDataProps.length &&
            deduped.supportedComputedProps.length === members.supportedComputedProps.length &&
            deduped.supportedMethodProps.length === members.supportedMethodProps.length;

        members = deduped;
        if (settled) {
            break;
        }
    }

    const injectNames = new Set(members.supportedInjectProps.map((p) => p.localName));
    const dataNames = new Set(members.supportedDataProps.map((p) => p.name));
    const computedNames = new Set(members.supportedComputedProps.map((p) => p.name));
    const methodNames = new Set(members.supportedMethodProps.map((p) => p.name));

    const supportedWatchProps = collectSupportedWatchProps(
        watchProps,
        unsupportedWatchEntries,
        propNames,
        dataNames,
        computedNames,
        methodNames,
        injectNames,
    );

    return {
        supportedInjectProps: members.supportedInjectProps,
        supportedDataProps: members.supportedDataProps,
        supportedComputedProps: members.supportedComputedProps,
        supportedMethodProps: members.supportedMethodProps,
        supportedWatchProps,
        watchProps,
        unsupportedWatchEntries,
        propNames,
        dataNames,
        computedNames,
        methodNames,
        injectNames,
        manualMigrationReasons,
        todoComments,
    };
}

function buildMemberContext(members: SupportedPublicMembers, propNames: Set<string>): RewriteContext {
    return {
        propNames,
        dataNames: new Set(members.supportedDataProps.map((p) => p.name)),
        computedNames: new Set(members.supportedComputedProps.map((p) => p.name)),
        methodNames: new Set(members.supportedMethodProps.map((p) => p.name)),
        injectNames: new Set(members.supportedInjectProps.map((p) => p.localName)),
    };
}

function collectManualFollowUps(optionsObj: ObjectLiteralExpression): ManualMigrationCollection {
    const manualMigrationReasons: string[] = [];
    const todoComments: string[] = [];

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

    // Runtime-relevant options that the codemod cannot translate and would
    // otherwise drop silently while reporting a successful migration.
    for (const option of UNSUPPORTED_TOP_LEVEL_OPTIONS) {
        if (optionsObj.getProperty(option)) {
            pushManualMigration(
                manualMigrationReasons,
                todoComments,
                option,
                `${option}: option is not supported by the SFC migration and requires manual migration`,
            );
        }
    }

    const injectProp = optionsObj.getProperty('inject');
    if (injectProp && !injectProp.isKind(SyntaxKind.PropertyAssignment)) {
        pushManualMigration(
            manualMigrationReasons,
            todoComments,
            'inject',
            'inject: shorthand inject declaration must be migrated manually',
        );
    }

    analyzeUnsupportedLifecycleHooks(optionsObj).forEach((reason) => {
        pushManualMigration(manualMigrationReasons, todoComments, 'lifecycle hook', reason);
    });

    return { manualMigrationReasons, todoComments };
}

const UNSUPPORTED_TOP_LEVEL_OPTIONS = [
    'beforeRouteEnter',
    'beforeRouteLeave',
    'beforeRouteUpdate',
    'metaInfo',
    'shortcuts',
    'errorCaptured',
    'expose',
    'extensionApiDevtoolInformation',
    'saveFinish',
];

function pushManualMigration(reasons: string[], todoComments: string[], label: string, reason: string): void {
    reasons.push(reason);
    todoComments.push(`// TODO: migrate ${label} manually: ${sanitizeTodoCommentText(reason)}`);
}

function filterInstanceDependentData(
    dataProps: DataProp[],
    ctx: RewriteContext,
    reasons: string[],
    todoComments: string[],
): DataProp[] {
    return dataProps.filter(({ name, valueText }) => {
        // Methods become `const` declarations emitted after the data refs, so a
        // data initializer that calls one would hit a temporal-dead-zone error.
        const calledMethod = findDataInitializerMethodCall(valueText, ctx.methodNames);
        if (calledMethod) {
            pushManualMigration(
                reasons,
                todoComments,
                'data entry',
                `data: ${name} initializer calls component method '${calledMethod}'`,
            );
            return false;
        }

        const unsupportedThis = findUnsupportedThisUsage({ text: valueText, kind: 'expression' }, ctx);
        if (unsupportedThis) {
            pushManualMigration(reasons, todoComments, 'data entry', `data: ${name} initializer uses ${unsupportedThis}`);
            return false;
        }

        return true;
    });
}

function filterInstanceDependentMethods(
    methodProps: MethodProp[],
    ctx: RewriteContext,
    reasons: string[],
    todoComments: string[],
): MethodProp[] {
    return methodProps.filter(({ name, bodyText, rawText }) => {
        const unsupportedThis = findUnsupportedThisUsage(
            { text: rawText ?? bodyText, kind: rawText === undefined ? 'body' : 'expression' },
            ctx,
        );
        if (unsupportedThis) {
            pushManualMigration(reasons, todoComments, 'method', `methods: ${name} uses ${unsupportedThis}`);
            return false;
        }

        return true;
    });
}

interface SupportedPublicMembers {
    supportedInjectProps: InjectProp[];
    supportedDataProps: DataProp[];
    supportedComputedProps: ComputedProp[];
    supportedMethodProps: MethodProp[];
}

/**
 * inject/data/computed/methods share one setup scope, so a name declared by two
 * of them would emit duplicate `const` declarations. Drop every member of a
 * colliding name and report it instead.
 */
function dropDuplicatePublicNames(
    members: SupportedPublicMembers,
    reasons: string[],
    todoComments: string[],
): SupportedPublicMembers {
    const counts = new Map<string, number>();
    const record = (name: string): void => {
        counts.set(name, (counts.get(name) ?? 0) + 1);
    };
    members.supportedInjectProps.forEach((p) => record(p.localName));
    members.supportedDataProps.forEach((p) => record(p.name));
    members.supportedComputedProps.forEach((p) => record(p.name));
    members.supportedMethodProps.forEach((p) => record(p.name));

    const duplicates = new Set(
        [...counts]
            .filter(
                ([
                    ,
                    count,
                ]) => count > 1,
            )
            .map(([name]) => name),
    );
    if (duplicates.size === 0) {
        return members;
    }

    duplicates.forEach((name) => {
        pushManualMigration(
            reasons,
            todoComments,
            'setup binding',
            `duplicate public name '${name}' across data, computed, methods, or inject`,
        );
    });

    return {
        supportedInjectProps: members.supportedInjectProps.filter((p) => !duplicates.has(p.localName)),
        supportedDataProps: members.supportedDataProps.filter((p) => !duplicates.has(p.name)),
        supportedComputedProps: members.supportedComputedProps.filter((p) => !duplicates.has(p.name)),
        supportedMethodProps: members.supportedMethodProps.filter((p) => !duplicates.has(p.name)),
    };
}

const PLACEHOLDER_INSTANCE_APIS = [
    '$el',
    '$parent',
    '$root',
    '$options',
    '$forceUpdate',
];

/**
 * These instance APIs are rewritten to transitional placeholders that change
 * runtime behavior, so their usage keeps the migration partial.
 */
function collectPlaceholderApiReasons(snippets: CodeSnippet[], reasons: string[], todoComments: string[]): void {
    for (const api of PLACEHOLDER_INSTANCE_APIS) {
        if (hasDirectThisPropertyUsage(snippets, api)) {
            pushManualMigration(
                reasons,
                todoComments,
                api,
                `${api}: instance API has no direct setup equivalent and requires manual migration`,
            );
        }
    }
}

function resolveComponentNameValue(optionsObj: ObjectLiteralExpression): {
    componentNameValue?: string;
    isDynamic: boolean;
} {
    const nameProp = optionsObj.getProperty('name');
    if (!nameProp?.isKind(SyntaxKind.PropertyAssignment)) {
        return { isDynamic: false };
    }

    const initializer = nameProp.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer();
    // Only string-literal names can be emitted into defineOptions; dynamic
    // expressions would produce an invalid or non-equivalent option.
    if (initializer?.isKind(SyntaxKind.StringLiteral)) {
        return { componentNameValue: initializer.getText(), isDynamic: false };
    }

    return { isDynamic: true };
}

function hasDynamicInheritAttrs(optionsObj: ObjectLiteralExpression): boolean {
    const prop = optionsObj.getProperty('inheritAttrs');
    if (!prop?.isKind(SyntaxKind.PropertyAssignment)) {
        return false;
    }

    const text = prop.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer()?.getText();
    return text !== 'true' && text !== 'false';
}

function collectSupportedNamedProps<T>(
    props: T[],
    getName: (prop: T) => string,
    reasonPrefix: string,
    todoLabel: string,
    manualMigrationReasons: string[],
    todoComments: string[],
): T[] {
    return props.filter((prop) => {
        const name = getName(prop);

        if (isSafeIdentifier(name)) {
            return true;
        }

        const reason = `${reasonPrefix}: ${name} is not a valid JavaScript identifier`;
        manualMigrationReasons.push(reason);
        todoComments.push(`// TODO: migrate ${todoLabel} manually: ${sanitizeTodoCommentText(reason)}`);
        return false;
    });
}

function collectUnsupportedEntries(
    entries: string[],
    reasonPrefix: string,
    todoLabel: string,
    manualMigrationReasons: string[],
    todoComments: string[],
): void {
    entries.forEach((entry) => {
        const reason = `${reasonPrefix}: ${sanitizeTodoCommentText(entry)}`;
        manualMigrationReasons.push(reason);
        todoComments.push(`// TODO: migrate ${todoLabel} manually: ${sanitizeTodoCommentText(reason)}`);
    });
}

function collectSupportedWatchProps(
    watchProps: WatchProp[],
    unsupportedWatchEntries: string[],
    propNames: Set<string>,
    dataNames: Set<string>,
    computedNames: Set<string>,
    methodNames: Set<string>,
    injectNames: Set<string>,
): WatchProp[] {
    return watchProps.filter((watchProp) => {
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
}

function collectSetupSnippets(
    supportedMembers: SupportedCompositionMembers,
    lifecycleHooks: LifecycleHook[],
): CodeSnippet[] {
    const { supportedDataProps, supportedComputedProps, supportedMethodProps, watchProps } = supportedMembers;

    // These snippets are the only source ranges that will be emitted into
    // setup. They drive import detection, template refs, inferred emits, and
    // `this.` rewriting without touching strings or comments elsewhere.
    return [
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
}

function collectVueImports(
    supportedMembers: SupportedCompositionMembers,
    templateRefNames: string[],
    usedComposables: UsedComposables,
    regularHooks: LifecycleHook[],
    allSnippets: CodeSnippet[],
): string[] {
    const { injectNames, supportedComputedProps, supportedDataProps, supportedInjectProps, supportedWatchProps } =
        supportedMembers;
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

    vueImports.push(...new Set(regularHooks.map((h) => h.compositionName as string)));

    return vueImports;
}

function collectPublicNames(supportedMembers: SupportedCompositionMembers): string[] {
    const { supportedInjectProps, supportedDataProps, supportedComputedProps, supportedMethodProps } = supportedMembers;

    return [
        ...supportedInjectProps.map((p) => p.localName),
        ...supportedDataProps.map((p) => p.name),
        ...supportedComputedProps.map((p) => p.name),
        ...supportedMethodProps.map((p) => p.name),
    ];
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

function collectBindingName(nameNode: BindingName, names: Set<string>): void {
    if (Node.isIdentifier(nameNode)) {
        names.add(nameNode.getText());
        return;
    }

    nameNode
        .getElements()
        .filter(Node.isBindingElement)
        .forEach((element) => {
            collectBindingName(element.getNameNode(), names);
        });
}
