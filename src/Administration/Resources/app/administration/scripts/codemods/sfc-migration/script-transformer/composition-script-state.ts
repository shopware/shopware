import type { BindingName, ObjectLiteralExpression, SourceFile } from 'ts-morph';
import { Node, SyntaxKind } from 'ts-morph';
import {
    collectGlobalAliasPaths,
    collectModuleBindingNames,
    collectModuleLocalNames,
    collectModuleVueImportNames,
    collectTrustedHelperNames,
    extractModuleLevelCode,
} from './ast';
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
import { extractExposeNames } from './extract-expose';
import { extractInjectProps } from './extract-inject';
import { analyzeUnsupportedLifecycleHooks, extractLifecycleHooks, LIFECYCLE_COMPOSITION_NAMES } from './extract-lifecycle';
import { extractMethodProps } from './extract-methods';
import { extractProvideEntries } from './extract-provide';
import { extractRouteGuards, ROUTE_GUARD_COMPOSABLE_NAMES } from './extract-route-guards';
import { extractWatchProps } from './extract-watch';
import type { WatchPath } from './helpers';
import { getWatchRootName, isDefined, isSafeIdentifier, parseWatchPath, sanitizeTodoCommentText } from './helpers';
import type { ResolvedIdentifiers } from './resolve-identifiers';
import { resolveIdentifierNames } from './resolve-identifiers';
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
    ExtractExposeResult,
    ExtractProvideResult,
    InjectProp,
    LifecycleHook,
    MethodProp,
    ProvideEntry,
    RewriteContext,
    RouteGuard,
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
    provideEntries: ProvideEntry[];
    lifecycleHooks: LifecycleHook[];
    regularHooks: LifecycleHook[];
    routeGuards: RouteGuard[];
    usedComposables: UsedComposables;
    templateRefNames: string[];
    publicNames: string[];
    /** Members listed in `defineExpose({ … })` — empty when the component exposes nothing. */
    exposeNames: string[];
    vueImports: string[];
    propNames: Set<string>;
    injectNames: Set<string>;
    manualMigrationReasons: string[];
    existingBindingNames: Set<string>;
    /** Names of the generated bindings, resolved around everything the component declares. */
    names: ResolvedIdentifiers;
    /**
     * Name of the generated root template ref that replaces `this.$el`, or null
     * when the component keeps the `$el` placeholder. The template transformer
     * writes the matching `ref="…"` attribute.
     */
    rootElementRefName: string | null;
}

export interface CollectCompositionScriptStateOptions {
    /** true when the template has an element the root ref can be placed on. */
    canHostRootElementRef?: boolean;
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
    declaredMemberNames: Set<string>;
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
    { canHostRootElementRef = false }: CollectCompositionScriptStateOptions = {},
): CompositionScriptState {
    let lifecycleHooks = extractLifecycleHooks(optionsObj);
    const inheritAttrs = extractInheritAttrs(optionsObj);
    const moduleLevelCode = extractModuleLevelCode(sourceFile, registration);
    const moduleLocalNames = collectModuleLocalNames(sourceFile, registration);
    const globalAliases = collectGlobalAliasPaths(sourceFile, registration);

    const manualMigrationReasons: string[] = [];
    const todoComments: string[] = [];

    // Unsupported props/emits shapes are suppressed to empty compiler macros so
    // no non-equivalent defineProps/defineEmits leaks; methods that depended on
    // the dropped members then surface as unresolved `this.<name>` follow-ups.
    const propsIssue = analyzePropsShape(optionsObj, moduleLocalNames, globalAliases);
    const propsText = propsIssue && !propsIssue.backoff ? null : extractPropsText(optionsObj, globalAliases);
    if (propsIssue && !propsIssue.backoff) {
        pushManualMigration(manualMigrationReasons, todoComments, 'props', propsIssue.reason);
    }

    const emitsIssue = analyzeEmitsShape(optionsObj, moduleLocalNames, globalAliases);
    const emitsDefinition =
        emitsIssue && !emitsIssue.backoff
            ? { keys: [], objectText: null }
            : extractEmitsDefinition(optionsObj, globalAliases);
    if (emitsIssue && !emitsIssue.backoff) {
        pushManualMigration(manualMigrationReasons, todoComments, 'emits', emitsIssue.reason);
    }

    const supportedMembers = collectSupportedCompositionMembers(
        optionsObj,
        propsText,
        collectModuleBindingNames(sourceFile, registration),
        collectTrustedHelperNames(sourceFile, registration, globalAliases),
    );
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
        declaredMemberNames,
    } = supportedMembers;

    const ctx: RewriteContext = {
        propNames,
        dataNames,
        computedNames,
        methodNames,
        injectNames,
        declaredMemberNames,
        // Filled in below, once the template's answer and the resolved names are
        // both known; `$el` is supported either way, so detection does not read it.
        rootElementRefName: null,
    };
    lifecycleHooks = filterSupported(
        lifecycleHooks,
        ({ hookName, bodyText }) => {
            const unsupportedThis = findUnsupportedSnippet([{ text: bodyText, kind: 'body' }], ctx);

            return unsupportedThis === null ? null : `${hookName}: lifecycle hook uses ${unsupportedThis}`;
        },
        (reason) => pushManualMigration(manualMigrationReasons, todoComments, 'lifecycle hook', reason),
    );

    const { routeGuards: extractedRouteGuards, unsupportedEntries: unsupportedRouteGuards } = extractRouteGuards(optionsObj);
    unsupportedRouteGuards.forEach((reason) => {
        pushManualMigration(manualMigrationReasons, todoComments, 'route guard', reason);
    });
    const routeGuards = filterSupported(
        extractedRouteGuards,
        ({ optionName, paramsText, bodyText }) => {
            const unsupportedThis = findUnsupportedSnippet([{ text: bodyText, kind: 'body', paramsText }], ctx);

            return unsupportedThis === null ? null : `${optionName}: route guard uses ${unsupportedThis}`;
        },
        (reason) => pushManualMigration(manualMigrationReasons, todoComments, 'route guard', reason),
    );

    const { provideEntries, unsupportedReason: provideUnsupportedReason } = collectProvideEntries(optionsObj, ctx);
    const allSnippets = collectSetupSnippets(supportedMembers, lifecycleHooks, provideEntries, routeGuards);

    const usedComposables = detectUsedComposables(allSnippets, supportedMembers.supportedWatchProps);
    const templateRefNames = collectThisRefNames(allSnippets);

    if (hasDirectThisPropertyUsage(allSnippets, '$store')) {
        manualMigrationReasons.push('$store usage requires manual migration to the appropriate Pinia store or composable');
    }

    const effectiveEmitsKeys =
        emitsDefinition.keys.length > 0 || emitsDefinition.objectText !== null
            ? emitsDefinition.keys
            : collectEmittedEventNames(allSnippets);

    const regularHooks = lifecycleHooks.filter((h) => h.compositionName !== null);
    const publicNames = collectPublicNames(supportedMembers);
    const existingBindingNames = collectExistingBindingNames(sourceFile);
    const names = resolveIdentifierNames(collectTakenNames(existingBindingNames, publicNames, templateRefNames, propNames));

    // `$el` becomes a real template ref whenever the template offers an element
    // to put it on; otherwise it keeps the placeholder and its manual follow-up.
    const rootElementRefName = canHostRootElementRef && hasDirectThisPropertyUsage(allSnippets, '$el') ? names.rootEl : null;
    ctx.rootElementRefName = rootElementRefName;

    collectPlaceholderApiReasons(allSnippets, manualMigrationReasons, todoComments, rootElementRefName !== null);

    const moduleVueImportNames = collectModuleVueImportNames(sourceFile, registration);
    const vueImports = collectVueImports(
        supportedMembers,
        templateRefNames,
        usedComposables,
        regularHooks,
        allSnippets,
        provideEntries,
        rootElementRefName,
        // A module that already imports the name from `vue` brings the very same
        // binding into the generated block, so importing it again would only
        // declare it twice.
    ).filter((name) => !moduleVueImportNames.has(name));
    const { exposeNames, unsupportedReason: exposeUnsupportedReason } = collectExposeNames(optionsObj, publicNames);

    if (exposeUnsupportedReason) {
        pushManualMigration(manualMigrationReasons, todoComments, 'expose', `expose: ${exposeUnsupportedReason}`);
    }

    collectPropShadowingReasons(templateRefNames, propNames, manualMigrationReasons, todoComments);
    const manualFollowUps = collectManualFollowUps(optionsObj, provideUnsupportedReason);

    manualMigrationReasons.push(...manualFollowUps.manualMigrationReasons);
    todoComments.push(...manualFollowUps.todoComments);

    manualMigrationReasons.push(...unsupportedWatchEntries.map((entry) => `watch: ${sanitizeTodoCommentText(entry)}`));

    const { componentNameValue, isDynamic: hasDynamicName } = resolveComponentNameValue(
        optionsObj,
        registration.componentName,
    );
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
        provideEntries,
        lifecycleHooks,
        regularHooks,
        routeGuards,
        usedComposables,
        templateRefNames,
        publicNames,
        exposeNames,
        vueImports,
        propNames,
        injectNames,
        manualMigrationReasons,
        existingBindingNames,
        names,
        rootElementRefName,
    };
}

function collectSupportedCompositionMembers(
    optionsObj: ObjectLiteralExpression,
    propsText: string | null,
    moduleBindingNames: Set<string>,
    trustedHelperNames: Set<string>,
): SupportedCompositionMembers {
    const { injectProps, unsupportedEntries: unsupportedInjectEntries } = extractInjectProps(optionsObj);
    const { dataProps, unsupportedEntries: unsupportedDataEntries } = extractDataProps(optionsObj);
    const { computedProps, unsupportedEntries: unsupportedComputedEntries } = extractComputedProps(
        optionsObj,
        trustedHelperNames,
        moduleBindingNames,
    );
    const { watchProps, unsupportedEntries } = extractWatchProps(optionsObj);
    const unsupportedWatchEntries = [...unsupportedEntries];
    const { methodProps, unsupportedEntries: unsupportedMethodEntries } = extractMethodProps(optionsObj, moduleBindingNames);
    const manualMigrationReasons: string[] = [];
    const todoComments: string[] = [];

    // Collected before any filtering, so a `this.<name>` reference to a member
    // that is dropped below can be reported as the cascade it is.
    const declaredMemberNames = new Set([
        ...injectProps.map((p) => p.localName),
        ...dataProps.map((p) => p.name),
        ...computedProps.map((p) => p.name),
        ...methodProps.map((p) => p.name),
    ]);

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
        const ctx = buildMemberContext(members, propNames, declaredMemberNames);
        const filteredData = filterSupported(
            members.supportedDataProps,
            ({ name, valueText }) => {
                // Methods become `const` declarations emitted after the data refs, so a
                // data initializer that calls one would hit a temporal-dead-zone error.
                const calledMethod = findDataInitializerMethodCall(valueText, ctx.methodNames);

                if (calledMethod) {
                    return `data: ${name} initializer calls component method '${calledMethod}'`;
                }

                const unsupportedThis = findUnsupportedSnippet([{ text: valueText, kind: 'expression' }], ctx);

                return unsupportedThis === null ? null : `data: ${name} initializer uses ${unsupportedThis}`;
            },
            (reason) => pushManualMigration(manualMigrationReasons, todoComments, 'data entry', reason),
        );
        const filteredComputed = filterSupported(
            members.supportedComputedProps,
            (prop) => {
                const snippets: CodeSnippet[] =
                    prop.kind === 'getter'
                        ? [{ text: prop.bodyText, kind: 'body' }]
                        : [
                              { text: prop.getterBodyText, kind: 'body' },
                              { text: prop.setterBodyText, kind: 'body', paramsText: prop.setterParam },
                          ];
                const unsupportedThis = findUnsupportedSnippet(snippets, ctx);

                return unsupportedThis === null ? null : `computed: ${prop.name} uses ${unsupportedThis}`;
            },
            (reason) => pushManualMigration(manualMigrationReasons, todoComments, 'computed entry', reason),
        );
        const filteredMethods = filterSupported(
            members.supportedMethodProps,
            ({ name, paramsText, bodyText, rawText }) => {
                const unsupportedThis = findUnsupportedSnippet(
                    [
                        {
                            text: rawText ?? bodyText,
                            kind: rawText === undefined ? 'body' : 'expression',
                            paramsText,
                        },
                    ],
                    ctx,
                );

                return unsupportedThis === null ? null : `methods: ${name} uses ${unsupportedThis}`;
            },
            (reason) => pushManualMigration(manualMigrationReasons, todoComments, 'method', reason),
        );
        const deduped = dropDuplicatePublicNames(
            {
                ...members,
                supportedDataProps: filteredData,
                supportedComputedProps: filteredComputed,
                supportedMethodProps: filteredMethods,
            },
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

    const watchCtx = buildMemberContext(members, propNames, declaredMemberNames);
    const supportedWatchProps = filterSupported(
        collectSupportedWatchProps(
            watchProps,
            unsupportedWatchEntries,
            propNames,
            dataNames,
            computedNames,
            methodNames,
            injectNames,
        ),
        ({ name, paramsText, bodyText }) => {
            // A watcher that only names a handler has no body of its own to check.
            if (!bodyText) {
                return null;
            }

            const unsupportedThis = findUnsupportedSnippet([{ text: bodyText, kind: 'body', paramsText }], watchCtx);

            return unsupportedThis === null ? null : `${name}: watcher uses ${unsupportedThis}`;
        },
        (reason) => {
            unsupportedWatchEntries.push(reason);
        },
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
        declaredMemberNames,
        manualMigrationReasons,
        todoComments,
    };
}

function buildMemberContext(
    members: SupportedPublicMembers,
    propNames: Set<string>,
    declaredMemberNames: Set<string>,
): RewriteContext {
    return {
        propNames,
        dataNames: new Set(members.supportedDataProps.map((p) => p.name)),
        computedNames: new Set(members.supportedComputedProps.map((p) => p.name)),
        methodNames: new Set(members.supportedMethodProps.map((p) => p.name)),
        injectNames: new Set(members.supportedInjectProps.map((p) => p.localName)),
        declaredMemberNames,
        // Only used when rewriting, and these contexts only ever filter.
        rootElementRefName: null,
    };
}

/**
 * A provided value that still depends on the instance cannot be rewritten, and a
 * `provide` migrated in part would silently change what descendants receive. So
 * one unrewritable value falls the whole option back to the manual TODO, naming
 * the key that could not be translated.
 */
function collectProvideEntries(optionsObj: ObjectLiteralExpression, ctx: RewriteContext): ExtractProvideResult {
    const result = extractProvideEntries(optionsObj);

    for (const { key, valueText } of result.provideEntries) {
        const unsupportedThis = findUnsupportedThisUsage({ text: valueText, kind: 'expression' }, ctx);

        if (unsupportedThis) {
            return { provideEntries: [], unsupportedReason: `${key} value uses ${unsupportedThis}` };
        }
    }

    return result;
}

/**
 * `expose` names the members the Options API kept reachable through a parent's
 * template ref. A name the codemod did not migrate cannot be listed in
 * `defineExpose`, and listing the rest would silently shrink that surface, so one
 * unmigrated entry falls the whole option back to the manual TODO naming it.
 */
function collectExposeNames(optionsObj: ObjectLiteralExpression, publicNames: string[]): ExtractExposeResult {
    const result = extractExposeNames(optionsObj);
    const unmigrated = result.exposeNames.find((name) => !publicNames.includes(name));

    if (unmigrated) {
        return {
            exposeNames: [],
            unsupportedReason: `'${unmigrated}' is not a migrated data, computed, method, or inject member`,
        };
    }

    return result;
}

function collectManualFollowUps(
    optionsObj: ObjectLiteralExpression,
    provideUnsupportedReason: string | null,
): ManualMigrationCollection {
    const manualMigrationReasons: string[] = [];
    const todoComments: string[] = [];

    // These options can affect runtime registration or lifecycle order. The
    // generated setup code is still useful, but a successful-looking migration
    // would be misleading without explicit manual follow-up markers.
    if (provideUnsupportedReason) {
        pushManualMigration(manualMigrationReasons, todoComments, 'provide', `provide: ${provideUnsupportedReason}`);
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
    // `beforeRouteLeave` and `beforeRouteUpdate` have composables and are
    // migrated; `beforeRouteEnter` runs before the instance exists, so there is
    // no setup call to register it from.
    'beforeRouteEnter',
    'metaInfo',
    'shortcuts',
    'errorCaptured',
    'extensionApiDevtoolInformation',
    'saveFinish',
];

function pushManualMigration(reasons: string[], todoComments: string[], label: string, reason: string): void {
    reasons.push(reason);
    todoComments.push(`// TODO: migrate ${label} manually: ${sanitizeTodoCommentText(reason)}`);
}

/**
 * Template refs share the setup scope with declared props, unlike `$refs` in the
 * Options API. The extension runtime strips prop keys from the returned setup
 * state, so a ref binding named after a prop is dropped and the template reads
 * `undefined`. The ref name is fixed by the template, so it cannot be renamed
 * automatically.
 */
function collectPropShadowingReasons(
    templateRefNames: string[],
    propNames: Set<string>,
    reasons: string[],
    todoComments: string[],
): void {
    templateRefNames
        .filter((name) => propNames.has(name))
        .forEach((name) => {
            pushManualMigration(
                reasons,
                todoComments,
                'template ref',
                `template ref '${name}' shadows the prop of the same name`,
            );
        });
}

/**
 * Drops every item whose snippet still depends on the Options API instance in a
 * way that cannot be rewritten into setup, and reports the reason instead of
 * emitting non-equivalent code. `findReason` returns the finished report line,
 * so a member can weigh several kinds of dependency in its own order.
 */
function filterSupported<T>(items: T[], findReason: (item: T) => string | null, report: (reason: string) => void): T[] {
    return items.filter((item) => {
        const reason = findReason(item);

        if (reason === null) {
            return true;
        }

        report(reason);

        return false;
    });
}

function findUnsupportedSnippet(snippets: CodeSnippet[], ctx: RewriteContext): string | null {
    for (const snippet of snippets) {
        const unsupportedThis = findUnsupportedThisUsage(snippet, ctx);

        if (unsupportedThis) {
            return unsupportedThis;
        }
    }

    return null;
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
 * runtime behavior, so their usage keeps the migration partial. `$el` is the one
 * that can be resolved for real: when the template hosts a generated root ref,
 * the rewrite is equivalent and nothing is reported.
 */
function collectPlaceholderApiReasons(
    snippets: CodeSnippet[],
    reasons: string[],
    todoComments: string[],
    hasRootElementRef: boolean,
): void {
    const placeholders = hasRootElementRef
        ? PLACEHOLDER_INSTANCE_APIS.filter((api) => api !== '$el')
        : PLACEHOLDER_INSTANCE_APIS;

    for (const api of placeholders) {
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

function resolveComponentNameValue(
    optionsObj: ObjectLiteralExpression,
    registeredName: string,
): {
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
        // Native setup infers the component name from the `.vue` filename, and the
        // file is written under the registered name, so an equal literal only
        // repeats what Vue already knows. A differing literal cannot change the
        // override target (the filename owns that), but it still sets Vue's own
        // component name — devtools, recursive self-reference — and is kept.
        if (initializer.getLiteralValue() === registeredName) {
            return { isDynamic: false };
        }

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
        const reason = findUnusableMemberNameReason(getName(prop), reasonPrefix);

        if (reason === null) {
            return true;
        }

        manualMigrationReasons.push(reason);
        todoComments.push(`// TODO: migrate ${todoLabel} manually: ${sanitizeTodoCommentText(reason)}`);
        return false;
    });
}

/**
 * Every name the generated setup can import. `emitImports` writes these at the
 * top of the block, so a member of the same name would emit a second declaration
 * of it — which the build rejects as `Identifier 'x' has already been declared`,
 * long after the codemod reported the component as migrated.
 *
 * The reservation is static: a name is reserved whether or not this component's
 * output ends up importing it. Whether an import is needed depends on which
 * members survive, and dropping a member can remove an import's last user, so a
 * per-component set would chase its own tail. Reserving up front is stable, only
 * ever drops more than strictly necessary, and across the whole Administration
 * the only members it hits are the ones that were broken anyway.
 *
 * Keep in sync with `collectVueImports` and `emitImports`.
 */
const RESERVED_IMPORT_NAMES = new Map<string, string>([
    ...[
        'ref',
        'computed',
        'inject',
        'provide',
        'watch',
        'unref',
        'nextTick',
        'useSlots',
        'useAttrs',
        'getCurrentInstance',
        ...LIFECYCLE_COMPOSITION_NAMES,
    ].map((name): [string, string] => [
        name,
        'vue',
    ]),
    // The bindings these composables are assigned to are renamed on collision by
    // resolve-identifiers.ts; the imported names themselves cannot be.
    ...[
        'useRouter',
        'useRoute',
        ...ROUTE_GUARD_COMPOSABLE_NAMES,
    ].map((name): [string, string] => [
        name,
        'vue-router',
    ]),
    [
        'useI18n',
        'vue-i18n',
    ],
]);

function findUnusableMemberNameReason(name: string, reasonPrefix: string): string | null {
    if (!isSafeIdentifier(name)) {
        return `${reasonPrefix}: ${name} is not a valid JavaScript identifier`;
    }

    const moduleSpecifier = RESERVED_IMPORT_NAMES.get(name);

    return moduleSpecifier === undefined
        ? null
        : `${reasonPrefix}: ${name} collides with the generated '${moduleSpecifier}' import of the same name`;
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
        const path = parseWatchPath(watchProp.name);

        if (!path) {
            unsupportedWatchEntries.push(`${watchProp.name}: watch path segments must be valid identifiers to be migrated`);
            return false;
        }

        // Vue 2 accepted string paths in watch definitions. In Composition API
        // we can only generate a safe source when that path starts at a prop,
        // data ref, computed ref, or inject declared by this codemod.
        const isKnownWatchTarget =
            propNames.has(path.root) ||
            dataNames.has(path.root) ||
            computedNames.has(path.root) ||
            injectNames.has(path.root);

        if (path.root !== '$route' && !isKnownWatchTarget) {
            unsupportedWatchEntries.push(`${watchProp.name}: ${describeUndeclaredWatchTarget(path)}`);

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

function describeUndeclaredWatchTarget({ root, propertyPath }: WatchPath): string {
    if (propertyPath.length > 0) {
        return `watch path root '${root}' is not declared in props, data, computed, or inject`;
    }

    return isSafeIdentifier(root)
        ? 'watch target is not declared in props, data, computed, or inject'
        : 'watch targets that are not valid identifiers must be migrated manually';
}

function collectSetupSnippets(
    supportedMembers: SupportedCompositionMembers,
    lifecycleHooks: LifecycleHook[],
    provideEntries: ProvideEntry[],
    routeGuards: RouteGuard[],
): CodeSnippet[] {
    const { supportedDataProps, supportedComputedProps, supportedMethodProps, supportedWatchProps } = supportedMembers;

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
                      { text: p.setterBodyText, kind: 'body' as const, paramsText: p.setterParam },
                  ],
        ),
        ...supportedWatchProps.map((p) =>
            p.bodyText ? { text: p.bodyText, kind: 'body' as const, paramsText: p.paramsText } : undefined,
        ),
        ...supportedMethodProps.map((p) => ({
            text: p.bodyText,
            kind: p.rawText === undefined ? ('body' as const) : ('expression' as const),
            paramsText: p.paramsText,
        })),
        ...lifecycleHooks.map((h) => ({ text: h.bodyText, kind: 'body' as const })),
        ...provideEntries.map((entry) => ({ text: entry.valueText, kind: 'expression' as const })),
        ...routeGuards.map((guard) => ({ text: guard.bodyText, kind: 'body' as const, paramsText: guard.paramsText })),
    ].filter(isDefined);
}

function collectVueImports(
    supportedMembers: SupportedCompositionMembers,
    templateRefNames: string[],
    usedComposables: UsedComposables,
    regularHooks: LifecycleHook[],
    allSnippets: CodeSnippet[],
    provideEntries: ProvideEntry[],
    rootElementRefName: string | null,
): string[] {
    const { injectNames, supportedComputedProps, supportedDataProps, supportedInjectProps, supportedWatchProps } =
        supportedMembers;
    const vueImports: string[] = [];

    if (supportedDataProps.length > 0 || templateRefNames.length > 0 || rootElementRefName) vueImports.push('ref');
    if (supportedComputedProps.length > 0) vueImports.push('computed');
    if (supportedInjectProps.length > 0) vueImports.push('inject');
    if (provideEntries.length > 0) vueImports.push('provide');
    if (supportedWatchProps.length > 0) vueImports.push('watch');
    if (supportedWatchProps.some(({ name }) => injectNames.has(getWatchRootName(name)))) vueImports.push('unref');
    if (usedComposables.needsNextTick) vueImports.push('nextTick');
    if (usedComposables.needsSlots) vueImports.push('useSlots');
    if (usedComposables.needsAttrs) vueImports.push('useAttrs');
    // A `$el` resolved into a root template ref needs no instance handle.
    if ((rootElementRefName === null && hasDirectThisPropertyUsage(allSnippets, '$el')) || usedComposables.needsDevice) {
        vueImports.push('getCurrentInstance');
    }

    vueImports.push(...new Set(regularHooks.map((h) => h.compositionName as string)));

    return vueImports;
}

function collectTakenNames(
    existingBindingNames: Set<string>,
    publicNames: string[],
    templateRefNames: string[],
    propNames: Set<string>,
): Set<string> {
    // Declared prop names count as taken: the extension runtime strips them from
    // the returned setup state, so a generated binding that shadows a prop would
    // be dropped and leave the template reading `undefined`.
    return new Set([
        ...existingBindingNames,
        ...publicNames,
        ...templateRefNames,
        ...propNames,
        'props',
    ]);
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
