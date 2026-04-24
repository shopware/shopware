import {
    MethodDeclaration,
    Node,
    ObjectLiteralExpression,
    Project,
    ScriptKind,
    SourceFile,
    SyntaxKind,
} from 'ts-morph';

// ---------------------------------------------------------------------------
// Public types
// ---------------------------------------------------------------------------

import type { MigrationStatus } from './types';
export type { MigrationStatus } from './types';

export interface TransformScriptResult {
    script: string;
    scriptType: 'setup' | 'options';
    status: MigrationStatus;
    blockers: string[];
    /** Names exposed in the `public:` return of createExtendableSetup — used by generate-sfc to build $dataScope. */
    publicNames: string[];
}

// ---------------------------------------------------------------------------
// Vue Composition API lifecycle-hook mapping
// ---------------------------------------------------------------------------

/**
 * Maps Options API lifecycle names to their Composition API equivalents.
 * `created` is intentionally absent — its body is emitted directly in setup()
 * (synchronous setup code is the Composition API equivalent of created()).
 */
const LIFECYCLE_MAP: Record<string, string> = {
    mounted: 'onMounted',
    beforeMount: 'onBeforeMount',
    // Vue 3 names
    beforeUnmount: 'onBeforeUnmount',
    unmounted: 'onUnmounted',
    // Vue 2 legacy names (kept for components that haven't fully adopted Vue 3 naming)
    beforeDestroy: 'onBeforeUnmount',
    destroyed: 'onUnmounted',
    updated: 'onUpdated',
    beforeUpdate: 'onBeforeUpdate',
    activated: 'onActivated',
    deactivated: 'onDeactivated',
};

// ---------------------------------------------------------------------------
// Internal extracted-data types
// ---------------------------------------------------------------------------

interface DataProp {
    name: string;
    /** Exact source text of the value, e.g. `'Default Title'`, `0`, `false` */
    valueText: string;
}

interface InjectProp {
    localName: string;
    sourceKey: string;
    defaultValueText?: string;
    treatDefaultAsFactory?: boolean;
}

interface ExtractInjectPropsResult {
    injectProps: InjectProp[];
    unsupportedEntries: string[];
}

type ComputedProp =
    | { name: string; kind: 'getter'; bodyText: string }
    | { name: string; kind: 'getter-setter'; getterBodyText: string; setterParam: string; setterBodyText: string };

interface WatchProp {
    name: string;
    paramsText: string;
    bodyText?: string;
    handlerName?: string;
    isAsync?: boolean;
    deep?: boolean;
    immediate?: boolean;
}

interface ExtractWatchPropsResult {
    watchProps: WatchProp[];
    unsupportedEntries: string[];
}

interface EmitsDefinition {
    keys: string[];
    objectText: string | null;
}

interface MethodProp {
    name: string;
    /** Full parameter list text including types and defaults */
    paramsText: string;
    bodyText: string;
    isAsync: boolean;
    /**
     * When set, the method is emitted verbatim as `const name = rawText;` after
     * `this.` rewriting — used for property-assignment methods like `debounce(...)`.
     */
    rawText?: string;
}

interface LifecycleHook {
    hookName: string;
    /** null means "run directly in setup" (i.e. created) */
    compositionName: string | null;
    bodyText: string;
}

type RewriteSnippetKind = 'body' | 'expression';

interface CodeSnippet {
    text: string;
    kind: RewriteSnippetKind;
}

interface RewriteContext {
    propNames: Set<string>;
    dataNames: Set<string>;
    computedNames: Set<string>;
    methodNames: Set<string>;
    /** inject() keys — accessed as plain identifiers in Composition API */
    injectNames: Set<string>;
}

interface UsedComposables {
    needsRouter: boolean;
    needsRoute: boolean;
    needsNextTick: boolean;
    needsSlots: boolean;
    needsI18n: boolean;
    needsEmit: boolean;
    needsAttrs: boolean;
}

interface UnsupportedInjectAnalysis {
    reasons: string[];
}

function isDefined<T>(value: T | undefined): value is T {
    return value !== undefined;
}

const RESERVED_IDENTIFIERS = new Set([
    'await',
    'break',
    'case',
    'catch',
    'class',
    'const',
    'continue',
    'debugger',
    'default',
    'delete',
    'do',
    'else',
    'enum',
    'export',
    'extends',
    'false',
    'finally',
    'for',
    'function',
    'if',
    'implements',
    'import',
    'in',
    'instanceof',
    'interface',
    'let',
    'new',
    'null',
    'package',
    'private',
    'protected',
    'public',
    'return',
    'static',
    'super',
    'switch',
    'this',
    'throw',
    'true',
    'try',
    'typeof',
    'var',
    'void',
    'while',
    'with',
    'yield',
]);

function isSafeIdentifier(name: string): boolean {
    return /^[$A-Z_a-z][$\w]*$/u.test(name) && !RESERVED_IDENTIFIERS.has(name);
}

function sanitizeTodoCommentText(value: string): string {
    return value.replace(/\r\n?|\n/g, ' ').replace(/\s+/g, ' ').trim();
}

function buildPropertyAccess(target: string, name: string): string {
    return isSafeIdentifier(name) ? `${target}.${name}` : `${target}[${quoteString(name)}]`;
}

function createWrappedSnippetSource(
    text: string,
    kind: RewriteSnippetKind,
): { sourceFile: SourceFile; snippetStart: number; snippetEnd: number } {
    const project = new Project({
        useInMemoryFileSystem: true,
        compilerOptions: { allowJs: true },
        skipAddingFilesFromTsConfig: true,
    });
    const prefix = kind === 'body' ? 'function __rewrite__() {\n' : 'const __rewrite__ = (';
    const suffix = kind === 'body' ? '\n}' : ');';

    return {
        sourceFile: project.createSourceFile('snippet.js', `${prefix}${text}${suffix}`, { scriptKind: ScriptKind.JS }),
        snippetStart: prefix.length,
        snippetEnd: prefix.length + text.length,
    };
}

function isNodeInsideSnippet(node: Node, snippetStart: number, snippetEnd: number): boolean {
    return node.getStart() >= snippetStart && node.getEnd() <= snippetEnd;
}

function getDirectThisPropertyName(node: import('ts-morph').PropertyAccessExpression): string | null {
    return node.getExpression().isKind(SyntaxKind.ThisKeyword) ? node.getName() : null;
}

function getThisRefName(node: import('ts-morph').PropertyAccessExpression): string | null {
    const expression = node.getExpression();

    if (!Node.isPropertyAccessExpression(expression)) {
        return null;
    }

    return getDirectThisPropertyName(expression) === '$refs' ? node.getName() : null;
}

function getSnippetPropertyAccesses(snippet: CodeSnippet): import('ts-morph').PropertyAccessExpression[] {
    const { sourceFile, snippetStart, snippetEnd } = createWrappedSnippetSource(snippet.text, snippet.kind);

    return sourceFile
        .getDescendantsOfKind(SyntaxKind.PropertyAccessExpression)
        .filter((node) => isNodeInsideSnippet(node, snippetStart, snippetEnd));
}

function getSnippetCallExpressions(snippet: CodeSnippet): import('ts-morph').CallExpression[] {
    const { sourceFile, snippetStart, snippetEnd } = createWrappedSnippetSource(snippet.text, snippet.kind);

    return sourceFile
        .getDescendantsOfKind(SyntaxKind.CallExpression)
        .filter((node) => isNodeInsideSnippet(node, snippetStart, snippetEnd));
}

function buildWatchSource(name: string, propNames: Set<string>, injectNames: Set<string>): string {
    if (propNames.has(name)) {
        return buildPropertyAccess('props', name);
    }

    if (name === '$route') {
        return `({ ...route, params: { ...route.params }, query: { ...route.query } })`;
    }

    if (injectNames.has(name)) {
        return `unref(${name})`;
    }

    return `${name}.value`;
}

function quoteString(value: string): string {
    return `'${value.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}'`;
}

function serializeMethodLikeFunction(method: MethodDeclaration): string {
    const asyncPrefix = method.isAsync() ? 'async ' : '';
    const paramsText = method.getParameters().map((param) => param.getText()).join(', ');
    const bodyText = method.getBodyText() ?? '';

    return `${asyncPrefix}function(${paramsText}) {${bodyText ? `\n${bodyText}\n` : ''}}`;
}

function getPropertyName(
    prop: import('ts-morph').PropertyAssignment | import('ts-morph').MethodDeclaration | import('ts-morph').ShorthandPropertyAssignment,
): string {
    const nameNode = prop.getNameNode();

    if (Node.isStringLiteral(nameNode) || Node.isNumericLiteral(nameNode)) {
        return nameNode.getLiteralText();
    }

    return prop.getName();
}

// ---------------------------------------------------------------------------
// AST helpers
// ---------------------------------------------------------------------------

function parseSource(jsContent: string): SourceFile {
    const project = new Project({
        useInMemoryFileSystem: true,
        compilerOptions: { allowJs: true },
        skipAddingFilesFromTsConfig: true,
    });

    return project.createSourceFile('component.js', jsContent, { scriptKind: ScriptKind.JS });
}

/**
 * Finds the `Shopware.Component.register(name, options)` call expression.
 * Returns `undefined` when the file does not contain one.
 */
function findRegisterCall(sourceFile: SourceFile) {
    return sourceFile
        .getDescendantsOfKind(SyntaxKind.CallExpression)
        .find((call) => /Shopware\.Component\.(register|extend)/.test(call.getExpression().getText()));
}

/**
 * Extracts the Options API object literal from the register/extend call arguments.
 *
 * - `Shopware.Component.register('name', { … })` — options at index 1
 * - `Shopware.Component.extend('name', 'parent', { … })` — options at index 2
 */
function findOptionsObject(sourceFile: SourceFile): ObjectLiteralExpression | undefined {
    const call = findRegisterCall(sourceFile);
    if (!call) return undefined;

    const isExtend = /Shopware\.Component\.extend/.test(call.getExpression().getText());
    const optionsArgIndex = isExtend ? 2 : 1;

    const arg = call.getArguments()[optionsArgIndex];
    return arg?.isKind(SyntaxKind.ObjectLiteralExpression)
        ? arg.asKindOrThrow(SyntaxKind.ObjectLiteralExpression)
        : undefined;
}

function extractComponentName(sourceFile: SourceFile): string {
    const call = findRegisterCall(sourceFile);
    if (!call) return 'unknown-component';

    const firstArg = call.getArguments()[0];
    return firstArg?.isKind(SyntaxKind.StringLiteral)
        ? firstArg.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue()
        : 'unknown-component';
}

// ---------------------------------------------------------------------------
// Section extractors — each walks a specific property of the options object
// ---------------------------------------------------------------------------

/**
 * Extracts `inject` entries while preserving aliases and defaults.
 */
function extractInjectProps(optionsObj: ObjectLiteralExpression): ExtractInjectPropsResult {
    const prop = optionsObj.getProperty('inject');
    if (!prop?.isKind(SyntaxKind.PropertyAssignment)) return { injectProps: [], unsupportedEntries: [] };

    const pa = prop.asKindOrThrow(SyntaxKind.PropertyAssignment);

    // Array form: inject: ['acl', 'repositoryFactory']
    const arrayInit = pa.getInitializerIfKind(SyntaxKind.ArrayLiteralExpression);
    if (arrayInit) {
        const injectProps: InjectProp[] = [];
        const unsupportedEntries: string[] = [];

        arrayInit.getElements().forEach((el) => {
            if (!el.isKind(SyntaxKind.StringLiteral)) {
                unsupportedEntries.push(`${el.getText()}: unsupported inject entry`);
                return;
            }

            const key = el.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue();
            injectProps.push({
                localName: key,
                sourceKey: key,
            });
        });

        return {
            injectProps,
            unsupportedEntries,
        };
    }

    // Object form: inject: { acl: 'acl', repositoryFactory: { from: 'repositoryFactory' } }
    const objInit = pa.getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);
    if (objInit) {
        const injectProps: InjectProp[] = [];
        const unsupportedEntries: string[] = [];

        objInit
            .getProperties()
            .forEach((p) => {
                if (p.isKind(SyntaxKind.ShorthandPropertyAssignment)) {
                    unsupportedEntries.push(`${getPropertyName(p)}: shorthand inject entries must be migrated manually`);
                    return;
                }

                if (!p.isKind(SyntaxKind.PropertyAssignment)) {
                    unsupportedEntries.push(`${p.getText()}: unsupported inject entry`);
                    return;
                }

                const assignment = p.asKindOrThrow(SyntaxKind.PropertyAssignment);
                const localName = getPropertyName(assignment);
                const stringInit = assignment.getInitializerIfKind(SyntaxKind.StringLiteral);

                if (stringInit) {
                    injectProps.push({
                        localName,
                        sourceKey: stringInit.getLiteralValue(),
                    });
                    return;
                }

                const objectInit = assignment.getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);

                if (!objectInit) {
                    unsupportedEntries.push(`${localName}: unsupported inject definition`);
                    return;
                }

                const hasUnsupportedObjectMembers = objectInit.getProperties().some((member) => {
                    if (member.isKind(SyntaxKind.PropertyAssignment)) {
                        const memberName = getPropertyName(member);

                        return memberName !== 'from' && memberName !== 'default';
                    }

                    return !(member.isKind(SyntaxKind.MethodDeclaration) && member.getName() === 'default');
                });

                if (hasUnsupportedObjectMembers) {
                    unsupportedEntries.push(`${localName}: unsupported inject definition`);
                    return;
                }

                const fromProp = objectInit.getProperty('from');
                if (fromProp && !fromProp.isKind(SyntaxKind.PropertyAssignment)) {
                    unsupportedEntries.push(`${localName}: unsupported inject definition`);
                    return;
                }

                const fromValue = fromProp?.isKind(SyntaxKind.PropertyAssignment)
                    ? fromProp.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializerIfKind(SyntaxKind.StringLiteral)?.getLiteralValue()
                    : undefined;

                if (fromProp && fromValue === undefined) {
                    unsupportedEntries.push(`${localName}: unsupported inject definition`);
                    return;
                }

                const defaultProp = objectInit.getProperty('default');
                const defaultInitializer = defaultProp?.isKind(SyntaxKind.PropertyAssignment)
                    ? defaultProp.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer()
                    : undefined;
                const defaultMethod = defaultProp?.isKind(SyntaxKind.MethodDeclaration)
                    ? defaultProp.asKindOrThrow(SyntaxKind.MethodDeclaration)
                    : undefined;

                injectProps.push({
                    localName,
                    sourceKey: fromValue ?? localName,
                    defaultValueText: defaultMethod
                        ? serializeMethodLikeFunction(defaultMethod)
                        : defaultInitializer?.getText(),
                    treatDefaultAsFactory: defaultInitializer?.isKind(SyntaxKind.ArrowFunction)
                        || defaultInitializer?.isKind(SyntaxKind.FunctionExpression)
                        || defaultMethod !== undefined,
                });
            });

        return { injectProps, unsupportedEntries };
    }

    return { injectProps: [], unsupportedEntries: ['inject must be an array or object literal'] };
}

function extractInlineFunctionHandler(
    handler: import('ts-morph').ArrowFunction | import('ts-morph').FunctionExpression,
): Pick<WatchProp, 'paramsText' | 'bodyText' | 'isAsync'> {
    const body = handler.getBody();

    return {
        isAsync: handler.isAsync(),
        paramsText: handler.getParameters().map((param) => param.getText()).join(', '),
        bodyText: body.isKind(SyntaxKind.Block)
            ? body.getStatements().map((statement) => statement.getText()).join('\n')
            : `return ${body.getText()};`,
    };
}

function parseWatchBooleanOption(
    optionName: 'deep' | 'immediate',
    optionProp: import('ts-morph').ObjectLiteralElementLike | undefined,
): { value?: boolean; unsupportedReason?: string } {
    if (!optionProp) {
        return {};
    }

    if (!optionProp.isKind(SyntaxKind.PropertyAssignment)) {
        return { unsupportedReason: `${optionName} must be a boolean literal` };
    }

    const initializerText = optionProp.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer()?.getText();

    if (initializerText === 'true') {
        return { value: true };
    }

    if (initializerText === 'false') {
        return { value: false };
    }

    return { unsupportedReason: `${optionName} must be a boolean literal` };
}

/**
 * `data() { return { key: value, … } }`
 * Returns `{ name, valueText }` for each property of the returned object.
 * `valueText` is the original source text so it can be passed directly to `ref(…)`.
 */
function extractDataProps(optionsObj: ObjectLiteralExpression): DataProp[] {
    const dataProp = optionsObj.getProperty('data');
    if (!dataProp) return [];

    let returnExpr: ObjectLiteralExpression | undefined;

    if (dataProp.isKind(SyntaxKind.MethodDeclaration)) {
        // data() { return { ... } }
        const body = dataProp.asKindOrThrow(SyntaxKind.MethodDeclaration).getBody();
        const returnStmt = body?.getDescendantsOfKind(SyntaxKind.ReturnStatement)[0];
        returnExpr = returnStmt?.getExpression()?.isKind(SyntaxKind.ObjectLiteralExpression)
            ? returnStmt.getExpression()!.asKindOrThrow(SyntaxKind.ObjectLiteralExpression)
            : undefined;
    } else if (dataProp.isKind(SyntaxKind.PropertyAssignment)) {
        const init = dataProp.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer();
        if (init?.isKind(SyntaxKind.ArrowFunction) || init?.isKind(SyntaxKind.FunctionExpression)) {
            const body = init.isKind(SyntaxKind.ArrowFunction)
                ? init.asKindOrThrow(SyntaxKind.ArrowFunction).getBody()
                : init.asKindOrThrow(SyntaxKind.FunctionExpression).getBody();
            if (body?.isKind(SyntaxKind.ParenthesizedExpression)) {
                // Arrow: () => ({ ... })
                const inner = body.asKindOrThrow(SyntaxKind.ParenthesizedExpression).getExpression();
                returnExpr = inner.isKind(SyntaxKind.ObjectLiteralExpression)
                    ? inner.asKindOrThrow(SyntaxKind.ObjectLiteralExpression)
                    : undefined;
            } else if (body?.isKind(SyntaxKind.Block)) {
                // Function: function() { return { ... } }
                const returnStmt = body.asKindOrThrow(SyntaxKind.Block).getDescendantsOfKind(SyntaxKind.ReturnStatement)[0];
                returnExpr = returnStmt?.getExpression()?.isKind(SyntaxKind.ObjectLiteralExpression)
                    ? returnStmt.getExpression()!.asKindOrThrow(SyntaxKind.ObjectLiteralExpression)
                    : undefined;
            }
        }
    }

    if (!returnExpr) return [];

    return returnExpr
        .getProperties()
        .filter((p) => p.isKind(SyntaxKind.PropertyAssignment))
        .map((p) => p.asKindOrThrow(SyntaxKind.PropertyAssignment))
        .map((p) => ({
            name: p.getName(),
            valueText: p.getInitializer()?.getText() ?? 'undefined',
        }));
}

/**
 * `computed: { getter() {…}, getterSetter: { get() {…}, set(v) {…} } }`
 *
 * A shorthand method declaration → plain getter.
 * A property assignment whose value is an object with `get`/`set` methods →
 * getter+setter computed.
 */
function extractComputedProps(optionsObj: ObjectLiteralExpression): ComputedProp[] {
    const computedProp = optionsObj.getProperty('computed');
    if (!computedProp?.isKind(SyntaxKind.PropertyAssignment)) return [];

    const computedObj = computedProp
        .asKindOrThrow(SyntaxKind.PropertyAssignment)
        .getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);
    if (!computedObj) return [];

    const result: ComputedProp[] = [];

    for (const prop of computedObj.getProperties()) {
        if (prop.isKind(SyntaxKind.MethodDeclaration)) {
            // canEdit() { return …; }
            const method = prop.asKindOrThrow(SyntaxKind.MethodDeclaration);
            result.push({ name: method.getName(), kind: 'getter', bodyText: method.getBodyText() ?? '' });
            continue;
        }

        if (prop.isKind(SyntaxKind.PropertyAssignment)) {
            // label: { get() {…}, set(val) {…} }
            const pa = prop.asKindOrThrow(SyntaxKind.PropertyAssignment);
            const innerObj = pa.getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);
            if (!innerObj) continue;

            const getterProp = innerObj.getProperty('get');
            const setterProp = innerObj.getProperty('set');

            if (
                getterProp?.isKind(SyntaxKind.MethodDeclaration) &&
                setterProp?.isKind(SyntaxKind.MethodDeclaration)
            ) {
                const getter = getterProp.asKindOrThrow(SyntaxKind.MethodDeclaration);
                const setter = setterProp.asKindOrThrow(SyntaxKind.MethodDeclaration);

                result.push({
                    name: pa.getName(),
                    kind: 'getter-setter',
                    getterBodyText: getter.getBodyText() ?? '',
                    setterParam: setter.getParameters()[0]?.getName() ?? 'val',
                    setterBodyText: setter.getBodyText() ?? '',
                });
            } else if (getterProp?.isKind(SyntaxKind.MethodDeclaration)) {
                // Getter-only object form: label: { get() { ... } }
                const getter = getterProp.asKindOrThrow(SyntaxKind.MethodDeclaration);
                result.push({
                    name: pa.getName(),
                    kind: 'getter',
                    bodyText: getter.getBodyText() ?? '',
                });
            }
        }
    }

    return result;
}

/**
 * `watch: { propName(newVal) {…} }`
 * Each watcher is a shorthand method declaration.
 */
function extractWatchProps(optionsObj: ObjectLiteralExpression): ExtractWatchPropsResult {
    const watchProp = optionsObj.getProperty('watch');
    if (!watchProp?.isKind(SyntaxKind.PropertyAssignment)) {
        return { watchProps: [], unsupportedEntries: [] };
    }

    const watchObj = watchProp
        .asKindOrThrow(SyntaxKind.PropertyAssignment)
        .getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);
    if (!watchObj) {
        return { watchProps: [], unsupportedEntries: ['watch must be an object literal'] };
    }

    const result: WatchProp[] = [];
    const unsupportedEntries: string[] = [];
    for (const p of watchObj.getProperties()) {
        if (p.isKind(SyntaxKind.MethodDeclaration)) {
            const method = p.asKindOrThrow(SyntaxKind.MethodDeclaration);
            result.push({
                name: getPropertyName(method),
                paramsText: method.getParameters().map((param) => param.getName()).join(', '),
                bodyText: method.getBodyText() ?? '',
            });
        } else if (p.isKind(SyntaxKind.PropertyAssignment)) {
            const pa = p.asKindOrThrow(SyntaxKind.PropertyAssignment);
            const name = getPropertyName(pa);
            const stringHandler = pa.getInitializerIfKind(SyntaxKind.StringLiteral);

            if (stringHandler) {
                result.push({
                    name,
                    paramsText: '',
                    handlerName: stringHandler.getLiteralValue(),
                });
                continue;
            }

            const innerObj = pa.getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);
            if (!innerObj) {
                unsupportedEntries.push(`${name}: unsupported watcher definition`);
                continue;
            }

            const handlerProp = innerObj.getProperty('handler');
            if (!handlerProp) {
                unsupportedEntries.push(`${name}: missing watcher handler`);
                continue;
            }

            const deepProp = innerObj.getProperty('deep');
            const immediProp = innerObj.getProperty('immediate');
            const deepOption = parseWatchBooleanOption('deep', deepProp);
            const immediateOption = parseWatchBooleanOption('immediate', immediProp);
            const unsupportedOptionReasons = [deepOption.unsupportedReason, immediateOption.unsupportedReason].filter(isDefined);

            if (unsupportedOptionReasons.length > 0) {
                unsupportedOptionReasons.forEach((reason) => {
                    unsupportedEntries.push(`${name}: ${reason}`);
                });
                continue;
            }

            const watchEntry: WatchProp = {
                name,
                paramsText: '',
                deep: deepOption.value,
                immediate: immediateOption.value,
            };

            if (handlerProp.isKind(SyntaxKind.MethodDeclaration)) {
                const handler = handlerProp.asKindOrThrow(SyntaxKind.MethodDeclaration);
                watchEntry.paramsText = handler.getParameters().map((param) => param.getName()).join(', ');
                watchEntry.bodyText = handler.getBodyText() ?? '';
                watchEntry.isAsync = handler.isAsync();
                result.push(watchEntry);
                continue;
            }

            if (handlerProp.isKind(SyntaxKind.PropertyAssignment)) {
                const handlerAssignment = handlerProp.asKindOrThrow(SyntaxKind.PropertyAssignment);
                const handlerValue = handlerAssignment.getInitializerIfKind(SyntaxKind.StringLiteral);

                if (handlerValue) {
                    watchEntry.handlerName = handlerValue.getLiteralValue();
                    result.push(watchEntry);
                    continue;
                }

                const inlineHandler = handlerAssignment.getInitializer();

                if (inlineHandler?.isKind(SyntaxKind.FunctionExpression) || inlineHandler?.isKind(SyntaxKind.ArrowFunction)) {
                    Object.assign(watchEntry, extractInlineFunctionHandler(inlineHandler));
                    result.push(watchEntry);
                    continue;
                }
            }

            unsupportedEntries.push(`${name}: unsupported watcher handler shape`);
        } else {
            unsupportedEntries.push(`${p.getText()}: unsupported watcher entry`);
            continue;
        }
    }
    return { watchProps: result, unsupportedEntries };
}

/**
 * `methods: { onSave() {…}, async loadItems() {…} }`
 */
function extractMethodProps(optionsObj: ObjectLiteralExpression): MethodProp[] {
    const methodsProp = optionsObj.getProperty('methods');
    if (!methodsProp?.isKind(SyntaxKind.PropertyAssignment)) return [];

    const methodsObj = methodsProp
        .asKindOrThrow(SyntaxKind.PropertyAssignment)
        .getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);
    if (!methodsObj) return [];

    const result: MethodProp[] = [];

    for (const prop of methodsObj.getProperties()) {
        if (prop.isKind(SyntaxKind.MethodDeclaration)) {
            const method = prop.asKindOrThrow(SyntaxKind.MethodDeclaration);
            result.push({
                name: method.getName(),
                paramsText: method
                    .getParameters()
                    .map((p) => p.getText())
                    .join(', '),
                bodyText: method.getBodyText() ?? '',
                isAsync: method.isAsync(),
            });
        } else if (prop.isKind(SyntaxKind.PropertyAssignment)) {
            // Handles patterns like `methodName: debounce(function() {...}, 300)`
            // where the method is expressed as a property value rather than a shorthand.
            const pa = prop.asKindOrThrow(SyntaxKind.PropertyAssignment);
            const name = pa.getName();
            const initializerText = pa.getInitializer()?.getText() ?? '';
            result.push({
                name,
                paramsText: '',
                bodyText: initializerText,
                isAsync: false,
                rawText: initializerText,
            });
        }
    }

    return result;
}

/**
 * Finds lifecycle hooks that are shorthand method declarations at the top level
 * of the options object (e.g. `mounted() {…}`).
 *
 * `created` is returned with `compositionName: null` to signal that its body
 * should be emitted directly in setup() rather than wrapped in a hook call.
 */
function extractLifecycleHooks(optionsObj: ObjectLiteralExpression): LifecycleHook[] {
    const result: LifecycleHook[] = [];

    for (const prop of optionsObj.getProperties()) {
        if (!prop.isKind(SyntaxKind.MethodDeclaration)) continue;

        const method = prop.asKindOrThrow(SyntaxKind.MethodDeclaration);
        const hookName = method.getName();

        if (hookName === 'created') {
            result.push({ hookName, compositionName: null, bodyText: method.getBodyText() ?? '' });
            continue;
        }

        const compositionName = LIFECYCLE_MAP[hookName];
        if (compositionName) {
            result.push({ hookName, compositionName, bodyText: method.getBodyText() ?? '' });
        }
    }

    return result;
}

/**
 * Returns the raw source text of `props: { … }` value for use in `defineProps()`.
 * Returns `null` when no props are defined.
 */
function extractPropsText(optionsObj: ObjectLiteralExpression): string | null {
    const prop = optionsObj.getProperty('props');
    if (!prop?.isKind(SyntaxKind.PropertyAssignment)) return null;

    const initializer = prop.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer();
    return initializer?.getText() ?? null;
}

function extractEmitsDefinition(optionsObj: ObjectLiteralExpression): EmitsDefinition {
    const prop = optionsObj.getProperty('emits');
    if (!prop?.isKind(SyntaxKind.PropertyAssignment)) return { keys: [], objectText: null };

    const pa = prop.asKindOrThrow(SyntaxKind.PropertyAssignment);

    const arrayInit = pa.getInitializerIfKind(SyntaxKind.ArrayLiteralExpression);
    if (arrayInit) {
        return {
            keys: arrayInit
                .getElements()
                .filter((el) => el.isKind(SyntaxKind.StringLiteral))
                .map((el) => el.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue()),
            objectText: null,
        };
    }

    const objInit = pa.getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);
    if (objInit) {
        return {
            keys: objInit.getProperties()
                .filter((p) => p.isKind(SyntaxKind.PropertyAssignment) || p.isKind(SyntaxKind.MethodDeclaration))
                .map((p) => p.isKind(SyntaxKind.MethodDeclaration)
                        ? p.asKindOrThrow(SyntaxKind.MethodDeclaration).getName()
                    : p.asKindOrThrow(SyntaxKind.PropertyAssignment).getName()),
            objectText: objInit.getText(),
        };
    }

    return { keys: [], objectText: null };
}

/**
 * Returns `true` when `inheritAttrs: false` is explicitly set in the options.
 */
function extractInheritAttrs(optionsObj: ObjectLiteralExpression): boolean {
    const prop = optionsObj.getProperty('inheritAttrs');
    if (!prop?.isKind(SyntaxKind.PropertyAssignment)) return true;

    const initializer = prop.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer();
    return initializer?.getText() !== 'false';
}

/**
 * Collects module-level code that appears before the `Shopware.Component.register()`
 * call, excluding the `import template from '…'` import.
 *
 * This preserves side-effect imports (e.g. `import './sw-avatar.scss'`) and
 * module-level variable declarations (e.g. `const { cloneDeep } = …`, `const colors = […]`).
 */
function extractModuleLevelCode(sourceFile: SourceFile): string {
    const registerCall = findRegisterCall(sourceFile);
    if (!registerCall) return '';

    const registerPos = registerCall.getStart();
    const lines: string[] = [];

    for (const stmt of sourceFile.getStatements()) {
        if (stmt.getStart() >= registerPos) break;

        // Drop `import template from '…'`
        if (stmt.isKind(SyntaxKind.ImportDeclaration)) {
            const imp = stmt.asKindOrThrow(SyntaxKind.ImportDeclaration);
            const defaultImport = imp.getDefaultImport()?.getText();
            if (defaultImport === 'template') continue;
        }

        lines.push(stmt.getText());
    }

    return lines.join('\n');
}

/**
 * Scans executable code snippets for `this.$refs.NAME` references and returns
 * the unique ref names. These need a `const NAME = ref(null)` declaration in setup.
 */
function collectThisRefNames(snippets: CodeSnippet[]): string[] {
    const names = new Set<string>();

    for (const snippet of snippets) {
        for (const node of getSnippetPropertyAccesses(snippet)) {
            const refName = getThisRefName(node);

            if (refName) {
                names.add(refName);
            }
        }
    }

    return [...names];
}

/**
 * Inspects a list of code snippets and reports which Vue Router / I18n / DOM
 * composables are needed based on executable `this.$xxx` references.
 */
function detectUsedComposables(snippets: CodeSnippet[], watchProps: WatchProp[]): UsedComposables {
    const usedComposables: UsedComposables = {
        needsRouter: false,
        needsRoute: watchProps.some((prop) => prop.name === '$route'),
        needsNextTick: false,
        needsSlots: false,
        needsI18n: false,
        needsEmit: false,
        needsAttrs: false,
    };

    for (const snippet of snippets) {
        for (const node of getSnippetPropertyAccesses(snippet)) {
            switch (getDirectThisPropertyName(node)) {
                case '$router':
                    usedComposables.needsRouter = true;
                    break;
                case '$route':
                    usedComposables.needsRoute = true;
                    break;
                case '$nextTick':
                    usedComposables.needsNextTick = true;
                    break;
                case '$slots':
                    usedComposables.needsSlots = true;
                    break;
                case '$tc':
                case '$t':
                    usedComposables.needsI18n = true;
                    break;
                case '$emit':
                    usedComposables.needsEmit = true;
                    break;
                case '$attrs':
                    usedComposables.needsAttrs = true;
                    break;
                default:
                    break;
            }
        }
    }

    return usedComposables;
}

/**
 * Scans method bodies for `this.$emit('eventName', …)` patterns and returns the
 * unique event name strings. Used to auto-populate `defineEmits` when the
 * Options API component did not declare an explicit `emits: […]` array.
 */
function collectEmittedEventNames(snippets: CodeSnippet[]): string[] {
    const names = new Set<string>();

    for (const snippet of snippets) {
        for (const node of getSnippetCallExpressions(snippet)) {
            const expression = node.getExpression();
            const firstArgument = node.getArguments()[0];

            if (
                Node.isPropertyAccessExpression(expression) &&
                getDirectThisPropertyName(expression) === '$emit' &&
                firstArgument?.isKind(SyntaxKind.StringLiteral)
            ) {
                names.add(firstArgument.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue());
            }
        }
    }

    return [...names];
}

/**
 * Rewrites executable `this.xxx` references in a method/computed/watch/lifecycle
 * body or expression so they are valid in a `<script setup>` Composition API context.
 *
 * Replacement order matters: special `$`-prefixed properties are handled first
 * to avoid conflicts with the named lookup loop that follows.
 */
function rewriteThisInBody(bodyText: string, ctx: RewriteContext, kind: RewriteSnippetKind = 'body'): string {
    const { sourceFile, snippetStart, snippetEnd } = createWrappedSnippetSource(bodyText, kind);
    const replacements = sourceFile
        .getDescendantsOfKind(SyntaxKind.PropertyAccessExpression)
        .filter((node) => isNodeInsideSnippet(node, snippetStart, snippetEnd))
        .map((node) => {
            const replacement = buildThisReplacement(node, ctx);

            if (!replacement) {
                return undefined;
            }

            return {
                start: node.getStart() - snippetStart,
                end: node.getEnd() - snippetStart,
                replacement,
            };
        })
        .filter(isDefined)
        .sort((a, b) => b.start - a.start || b.end - a.end);

    let result = bodyText;
    let lastReplacedStart = bodyText.length + 1;

    for (const { start, end, replacement } of replacements) {
        if (end > lastReplacedStart) {
            continue;
        }

        result = result.slice(0, start) + replacement + result.slice(end);
        lastReplacedStart = start;
    }

    return result;
}

function buildThisReplacement(node: import('ts-morph').PropertyAccessExpression, ctx: RewriteContext): string | null {
    const refName = getThisRefName(node);

    if (refName) {
        return `${refName}.value`;
    }

    const name = getDirectThisPropertyName(node);

    if (!name) {
        return null;
    }

    switch (name) {
        case '$emit':
            return 'emit';
        case '$router':
            return 'router';
        case '$route':
            return 'route';
        case '$nextTick':
            return 'nextTick';
        case '$slots':
            return 'slots';
        case '$props':
            return 'props';
        case '$attrs':
            return 'attrs';
        case '$tc':
            return 'tc';
        case '$t':
            return 't';
        case '$el':
            return '/* TODO: $el */ getCurrentInstance()?.proxy?.$el';
        case '$store':
            return "/* TODO: migrate $store to composable */\n        (() => { throw new Error('$store used here — replace with the appropriate Pinia store or composable before shipping'); })()";
        case '$parent':
            return '/* TODO: $parent */ undefined';
        case '$root':
            return '/* TODO: $root */ undefined';
        case '$options':
            return '/* TODO: $options */ {}';
        case '$forceUpdate':
            return '/* TODO: $forceUpdate */ (() => {})';
        default:
            break;
    }

    if (ctx.propNames.has(name)) {
        return buildPropertyAccess('props', name);
    }

    if (ctx.dataNames.has(name) || ctx.computedNames.has(name)) {
        return `${name}.value`;
    }

    if (ctx.methodNames.has(name) || ctx.injectNames.has(name)) {
        return name;
    }

    return null;
}

/**
 * Detects Options API features that block full Composition API migration.
 * Hard blockers (render) prevent any SFC output; soft blockers (mixins, extends)
 * trigger an Options API backoff instead.
 */
function detectBlockers(optionsObj: ObjectLiteralExpression, sourceFile: SourceFile): string[] {
    const blockers: string[] = [];

    const registerCall = findRegisterCall(sourceFile);
    const isExtend = /Shopware\.Component\.extend/.test(registerCall?.getExpression().getText() ?? '');
    if (isExtend) {
        const parentArg = registerCall?.getArguments()[1];
        const parentName = parentArg?.isKind(SyntaxKind.StringLiteral)
            ? parentArg.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue()
            : null;
        blockers.push(parentName ? `extends (parent: ${parentName})` : 'extends');
    }
    if (optionsObj.getProperty('mixins')) blockers.push('mixins');
    if (optionsObj.getProperty('render')) blockers.push('render function');

    return blockers;
}

function analyzeUnsupportedInjectEntries(optionsObj: ObjectLiteralExpression): UnsupportedInjectAnalysis {
    const { injectProps, unsupportedEntries } = extractInjectProps(optionsObj);
    const reasons = [
        ...unsupportedEntries.map((entry) => `inject: ${sanitizeTodoCommentText(entry)}`),
        ...injectProps
            .filter(({ localName }) => !isSafeIdentifier(localName))
            .map(({ localName }) => `inject: ${localName} is not a valid JavaScript identifier`),
    ];

    return { reasons: [...new Set(reasons)] };
}

// ---------------------------------------------------------------------------
// Code generators
// ---------------------------------------------------------------------------

function buildCompositionApiScript(optionsObj: ObjectLiteralExpression, componentName: string, sourceFile: SourceFile, useDataScope: boolean): { script: string; publicNames: string[]; manualMigrationReasons: string[] } {
    const { injectProps, unsupportedEntries: unsupportedInjectEntries } = extractInjectProps(optionsObj);
    const dataProps = extractDataProps(optionsObj);
    const computedProps = extractComputedProps(optionsObj);
    const { watchProps, unsupportedEntries: unsupportedWatchEntries } = extractWatchProps(optionsObj);
    const methodProps = extractMethodProps(optionsObj);
    const lifecycleHooks = extractLifecycleHooks(optionsObj);
    const propsText = extractPropsText(optionsObj);
    const emitsDefinition = extractEmitsDefinition(optionsObj);
    const inheritAttrs = extractInheritAttrs(optionsObj);
    const moduleLevelCode = extractModuleLevelCode(sourceFile);
    const manualMigrationReasons: string[] = [];
    const todoComments: string[] = [];

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

    const supportedDataProps = dataProps.filter(({ name }) => {
        if (isSafeIdentifier(name)) {
            return true;
        }

        const reason = `data: ${name} is not a valid JavaScript identifier`;
        manualMigrationReasons.push(reason);
        todoComments.push(`// TODO: migrate data entry manually: ${sanitizeTodoCommentText(reason)}`);
        return false;
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

    const supportedMethodProps = methodProps.filter(({ name }) => {
        if (isSafeIdentifier(name)) {
            return true;
        }

        const reason = `methods: ${name} is not a valid JavaScript identifier`;
        manualMigrationReasons.push(reason);
        todoComments.push(`// TODO: migrate method manually: ${sanitizeTodoCommentText(reason)}`);
        return false;
    });

    const injectNames = new Set(supportedInjectProps.map((p) => p.localName));
    const propNames = new Set(propsText ? extractPropNamesFromText(optionsObj) : []);
    const dataNames = new Set(supportedDataProps.map((p) => p.name));
    const computedNames = new Set(supportedComputedProps.map((p) => p.name));
    const methodNames = new Set(supportedMethodProps.map((p) => p.name));

    const ctx: RewriteContext = { propNames, dataNames, computedNames, methodNames, injectNames };

    // Collect all executable snippets to scan for composable usage and template refs.
    const allSnippets = [
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

    // Determine the final emits list: prefer explicit `emits: [...]`, fall back to
    // scanning method bodies for `this.$emit('eventName', ...)` calls.
    const effectiveEmitsKeys =
        emitsDefinition.keys.length > 0 || emitsDefinition.objectText !== null
            ? emitsDefinition.keys
            : collectEmittedEventNames(allSnippets);

    const supportedWatchProps = watchProps.filter((watchProp) => {
        if (watchProp.name.includes('.')) {
            unsupportedWatchEntries.push(`${watchProp.name}: nested watch paths are not supported`);
            return false;
        }

        if (watchProp.name !== '$route' && !propNames.has(watchProp.name) && !injectNames.has(watchProp.name) && !isSafeIdentifier(watchProp.name)) {
            unsupportedWatchEntries.push(`${watchProp.name}: watch targets that are not valid identifiers must be migrated manually`);
            return false;
        }

        if (watchProp.handlerName && !methodNames.has(watchProp.handlerName)) {
            unsupportedWatchEntries.push(`${watchProp.name}: string handler '${watchProp.handlerName}' was not found in methods`);
            return false;
        }

        return true;
    });

    // Determine which Vue composables are actually needed
    const vueImports: string[] = [];
    if (supportedDataProps.length > 0 || templateRefNames.length > 0) vueImports.push('ref');
    if (supportedComputedProps.length > 0) vueImports.push('computed');
    if (supportedInjectProps.length > 0) vueImports.push('inject');
    if (supportedWatchProps.length > 0) vueImports.push('watch');
    if (supportedWatchProps.some(({ name }) => injectNames.has(name))) vueImports.push('unref');
    if (usedComposables.needsNextTick) vueImports.push('nextTick');
    if (usedComposables.needsSlots) vueImports.push('useSlots');
    if (usedComposables.needsAttrs) vueImports.push('useAttrs');
    // Check whether we need getCurrentInstance for executable $el handling
    const needsGetCurrentInstance = allSnippets.some((snippet) =>
        getSnippetPropertyAccesses(snippet).some((node) => getDirectThisPropertyName(node) === '$el'),
    );
    if (needsGetCurrentInstance) vueImports.push('getCurrentInstance');
    if (useDataScope) vueImports.push('reactive');

    const regularHooks = lifecycleHooks.filter((h) => h.compositionName !== null);
    vueImports.push(...new Set(regularHooks.map((h) => h.compositionName as string)));

    // All names exposed to the template via the `public:` return + top-level destructuring.
    // Template refs are declared outside createExtendableSetup as module-level refs so they
    // do not need to be in publicNames — the template can access them directly.
    const publicNames = [
        ...supportedInjectProps.map((p) => p.localName),
        ...supportedDataProps.map((p) => p.name),
        ...supportedComputedProps.map((p) => p.name),
        ...supportedMethodProps.map((p) => p.name),
    ];

    if (optionsObj.getProperty('provide')) {
        todoComments.push('// TODO: migrate `provide` manually — map each key to provide(key, value) calls');
    }
    if (optionsObj.getProperty('components')) {
        todoComments.push('// TODO: verify local component registrations in `components:` — remove if globally registered');
    }
    if (optionsObj.getProperty('directives')) {
        todoComments.push('// TODO: migrate `directives` manually');
    }
    if (optionsObj.getProperty('beforeCreate')) {
        todoComments.push('// TODO: `beforeCreate` was dropped — move logic to top of setup if needed');
    }

    const lines: string[] = [];

    if (todoComments.length > 0) {
        lines.push(todoComments.join('\n'));
        lines.push('');
    }

    // ── module-level code (scss imports, cloneDeep, colors, etc.) ────────────
    if (moduleLevelCode) {
        lines.push(moduleLevelCode);
        lines.push('');
    }

    // ── Vue compiler macros (defineOptions, defineProps, defineEmits) ─────────
    const componentNameProp = optionsObj.getProperty('name');
    const nameValue = componentNameProp?.isKind(SyntaxKind.PropertyAssignment)
        ? componentNameProp.asKindOrThrow(SyntaxKind.PropertyAssignment).getInitializer()?.getText()
        : undefined;
    const defineOptionsArgs = [
        !inheritAttrs ? 'inheritAttrs: false' : '',
        nameValue ? `name: ${nameValue}` : '',
    ].filter(Boolean);
    if (defineOptionsArgs.length > 0) {
        lines.push(`defineOptions({ ${defineOptionsArgs.join(', ')} });`);
        lines.push('');
    }

    if (propsText) {
        lines.push(`const props = defineProps(${propsText});`);
    } else {
        lines.push(`const props = defineProps({});`);
    }

    if (emitsDefinition.objectText !== null) {
        lines.push(`const emit = defineEmits(${emitsDefinition.objectText});`);
    } else if (effectiveEmitsKeys.length > 0) {
        const emitsList = effectiveEmitsKeys.map((k) => `'${k}'`).join(', ');
        lines.push(`const emit = defineEmits([${emitsList}]);`);
    } else if (usedComposables.needsEmit) {
        // $emit is used somewhere but no events could be statically detected;
        // generate an empty defineEmits so the identifier is at least valid.
        lines.push(`const emit = defineEmits([]);`);
    }
    lines.push('');

    // ── imports ──────────────────────────────────────────────────────────────
    lines.push(`import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';`);
    if (vueImports.length > 0) {
        lines.push(`import { ${[...new Set(vueImports)].join(', ')} } from 'vue';`);
    }

    // Vue Router composable imports (useRouter, useRoute are from 'vue-router')
    const routerImports: string[] = [];
    if (usedComposables.needsRouter) routerImports.push('useRouter');
    if (usedComposables.needsRoute) routerImports.push('useRoute');
    if (routerImports.length > 0) {
        lines.push(`import { ${routerImports.join(', ')} } from 'vue-router';`);
    }
    // useSlots is from 'vue', add it to vueImports instead
    if (usedComposables.needsI18n) {
        lines.push(`import { useI18n } from 'vue-i18n';`);
    }
    lines.push('');

    // ── composable declarations ───────────────────────────────────────────────
    if (usedComposables.needsRouter) lines.push(`const router = useRouter();`);
    if (usedComposables.needsRoute) lines.push(`const route = useRoute();`);
    if (usedComposables.needsSlots) lines.push(`const slots = useSlots();`);
    if (usedComposables.needsAttrs) lines.push(`const attrs = useAttrs();`);
    if (usedComposables.needsI18n) lines.push(`const { t, tc } = useI18n();`);
    const hasComposableDeclarations =
        usedComposables.needsRouter ||
        usedComposables.needsRoute ||
        usedComposables.needsSlots ||
        usedComposables.needsAttrs ||
        usedComposables.needsI18n;
    if (hasComposableDeclarations) {
        lines.push('');
    }

    // ── template ref declarations ─────────────────────────────────────────────
    for (const refName of templateRefNames) {
        lines.push(`const ${refName} = ref(null);`);
    }
    if (templateRefNames.length > 0) lines.push('');

    // ── createExtendableSetup call + destructuring ────────────────────────────
    if (publicNames.length > 0) {
        lines.push('const {');
        publicNames.forEach((n) => lines.push(`    ${n},`));
        lines.push('} = createExtendableSetup(');
    } else {
        lines.push('createExtendableSetup(');
    }

    lines.push('    {');
    lines.push(`        name: '${componentName}',`);
    lines.push('        props,');
    lines.push('    },');
    lines.push('    () => {');

    // ── inject ────────────────────────────────────────────────────────────────
    supportedInjectProps.forEach(({ localName, sourceKey, defaultValueText, treatDefaultAsFactory }) => {
        const args = [quoteString(sourceKey)];

        if (defaultValueText !== undefined) {
            args.push(defaultValueText);

            if (treatDefaultAsFactory) {
                args.push('true');
            }
        }

        lines.push(`        const ${localName} = inject(${args.join(', ')});`);
    });
    if (supportedInjectProps.length > 0) lines.push('');

    // ── data → ref() ─────────────────────────────────────────────────────────
    supportedDataProps.forEach(({ name, valueText }) => {
        // Data initializers may reference props via `this.propName` — rewrite those
        const rewrittenValue = rewriteThisInBody(valueText, ctx, 'expression');
        lines.push(`        const ${name} = ref(${rewrittenValue});`);
    });
    if (supportedDataProps.length > 0) lines.push('');

    // ── computed ──────────────────────────────────────────────────────────────
    supportedComputedProps.forEach((prop) => {
        if (prop.kind === 'getter') {
            const body = rewriteThisInBody(prop.bodyText, ctx);
            lines.push(`        const ${prop.name} = computed(() => {`);
            lines.push(indentBlock(body, 12));
            lines.push(`        });`);
        } else {
            const getterBody = rewriteThisInBody(prop.getterBodyText, ctx);
            const setterBody = rewriteThisInBody(prop.setterBodyText, ctx);
            lines.push(`        const ${prop.name} = computed({`);
            lines.push(`            get: () => {`);
            lines.push(indentBlock(getterBody, 16));
            lines.push(`            },`);
            lines.push(`            set: (${prop.setterParam}) => {`);
            lines.push(indentBlock(setterBody, 16));
            lines.push(`            },`);
            lines.push(`        });`);
        }
    });
    if (supportedComputedProps.length > 0) lines.push('');

    // ── methods ───────────────────────────────────────────────────────────────
    supportedMethodProps.forEach(({ name, paramsText, bodyText, isAsync, rawText }) => {
        if (rawText !== undefined) {
            // Property-assignment method (e.g. `debounce(...)`): emit verbatim after this-rewriting.
            let rewritten = rewriteThisInBody(rawText, ctx, 'expression');
            rewritten = rewritten.replace(/\bfunction\s+\w*\s*\(([^)]*)\)\s*\{/g, '($1) => {');
            lines.push(`        const ${name} = ${rewritten};`);
        } else {
            const asyncKw = isAsync ? 'async ' : '';
            const body = rewriteThisInBody(bodyText, ctx);
            lines.push(`        const ${name} = ${asyncKw}(${paramsText}) => {`);
            lines.push(indentBlock(body, 12));
            lines.push(`        };`);
        }
    });
    if (supportedMethodProps.length > 0) lines.push('');

    // ── watch ─────────────────────────────────────────────────────────────────
    unsupportedWatchEntries.forEach((entry) => {
        lines.push(`        // TODO: migrate watch entry manually: ${sanitizeTodoCommentText(entry)}`);
    });
    if (unsupportedWatchEntries.length > 0) lines.push('');

    supportedWatchProps.forEach(({ name, paramsText, bodyText, handlerName, isAsync, deep, immediate }) => {
        const source = buildWatchSource(name, propNames, injectNames);
        const hasOptions = deep || immediate;
        const optionsParts = [deep ? 'deep: true' : '', immediate ? 'immediate: true' : ''].filter(Boolean);

        if (handlerName) {
            lines.push(`        watch(() => ${source}, (...args) => ${handlerName}(...args)${hasOptions ? `, { ${optionsParts.join(', ')} }` : ''});`);
            return;
        }

        const body = rewriteThisInBody(bodyText ?? '', ctx);
        const asyncPrefix = isAsync ? 'async ' : '';
        const paramPart = paramsText ? `${asyncPrefix}(${paramsText}) => {` : `${asyncPrefix}() => {`;
        lines.push(`        watch(() => ${source}, ${paramPart}`);
        lines.push(indentBlock(body, 12));
        lines.push(hasOptions ? `        }, { ${optionsParts.join(', ')} });` : `        });`);
    });
    if (supportedWatchProps.length > 0) lines.push('');

    manualMigrationReasons.push(...unsupportedWatchEntries.map((entry) => `watch: ${sanitizeTodoCommentText(entry)}`));

    // ── created() body runs synchronously inside setup, giving it access to
    //    inject values. This is the Composition API equivalent of created().
    const createdHooks = lifecycleHooks.filter((h) => h.compositionName === null);
    if (createdHooks.length > 0) {
        for (const hook of createdHooks) {
            const body = rewriteThisInBody(hook.bodyText, ctx);
            lines.push(indentBlock(body.trim(), 8));
        }
        lines.push('');
    }

    // ── lifecycle hooks (excluding created, which ran directly above) ─────────
    for (const { compositionName, bodyText } of regularHooks) {
        const body = rewriteThisInBody(bodyText, ctx);
        lines.push(`        ${compositionName}(() => {`);
        lines.push(indentBlock(body, 12));
        lines.push(`        });`);
    }
    if (regularHooks.length > 0) lines.push('');

    // ── public return ─────────────────────────────────────────────────────────
    lines.push('        return {');
    lines.push('            public: {');
    publicNames.forEach((n) => lines.push(`                ${n},`));
    lines.push('            },');
    lines.push('        };');
    lines.push('    },');
    lines.push(');');

    if (useDataScope) {
        lines.push('');
        lines.push(`const $dataScope = reactive({ ${publicNames.join(', ')} });`);
    }

    return { script: lines.join('\n'), publicNames, manualMigrationReasons: [...new Set(manualMigrationReasons)] };
}

/**
 * Indents each non-empty line in `block` by `spaces` spaces.
 * Preserves blank lines without adding trailing whitespace.
 */
function indentBlock(block: string, spaces: number): string {
    const pad = ' '.repeat(spaces);
    return block
        .split('\n')
        .map((line) => (line.trim() === '' ? '' : pad + line))
        .join('\n');
}

/**
 * Extracts the prop names from the options object's `props` property.
 * Used to build the `propNames` set for `this` rewriting.
 */
function extractPropNamesFromText(optionsObj: ObjectLiteralExpression): string[] {
    const prop = optionsObj.getProperty('props');
    if (!prop?.isKind(SyntaxKind.PropertyAssignment)) return [];

    const pa = prop.asKindOrThrow(SyntaxKind.PropertyAssignment);

    // Array form: props: ['label', 'value']
    const arrayInit = pa.getInitializerIfKind(SyntaxKind.ArrayLiteralExpression);
    if (arrayInit) {
        return arrayInit
            .getElements()
            .filter((el) => el.isKind(SyntaxKind.StringLiteral))
            .map((el) => el.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue());
    }

    const initializer = pa.getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);

    return (
        initializer
            ?.getProperties()
            .filter(
                (p) =>
                    p.isKind(SyntaxKind.PropertyAssignment) || p.isKind(SyntaxKind.MethodDeclaration),
            )
            .map((p) => getPropertyName(
                p.isKind(SyntaxKind.PropertyAssignment)
                    ? p.asKindOrThrow(SyntaxKind.PropertyAssignment)
                    : p.asKindOrThrow(SyntaxKind.MethodDeclaration),
            )) ?? []
    );
}

/**
 * Preserves the original Options API source, removing only the template import
 * and matching top-level component option (both are replaced by the `<template>`
 * section in the generated SFC).
 */
function buildOptionsApiBackoff(sourceFile: SourceFile): string {
    const project = new Project({
        useInMemoryFileSystem: true,
        compilerOptions: { allowJs: true },
        skipAddingFilesFromTsConfig: true,
    });
    const clone = project.createSourceFile('component.js', sourceFile.getFullText(), { scriptKind: ScriptKind.JS });

    const templateImport = clone
        .getImportDeclarations()
        .find((imp) => imp.getDefaultImport()?.getText() === 'template');

    templateImport?.remove();

    const optionsObj = findOptionsObject(clone);
    optionsObj?.getProperty('template')?.remove();

    return clone.getFullText().trim();
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Transforms a Shopware Options API `index.js` file into either:
 *
 * - A `<script setup>` body wrapping all state in `createExtendableSetup` so
 *   the component remains extensible via `overrideComponentSetup` after migration.
 * - A plain `<script>` body (Options API backoff) when soft blockers like
 *   `mixins` prevent automatic migration.
 * - An empty string for components with hard blockers (`render()`).
 *
 * JS analysis and `this.*` rewrites use the TypeScript compiler's AST (through
 * ts-morph) so executable code can be transformed without touching strings,
 * comments, or static template-literal text.
 */
export function transformScript(jsContent: string, useDataScope = false): TransformScriptResult {
    const sourceFile = parseSource(jsContent);
    const optionsObj = findOptionsObject(sourceFile);
    const componentName = extractComponentName(sourceFile);

    if (!optionsObj) {
        return { script: '', scriptType: 'options', status: 'not-migratable', blockers: ['no options object found'], publicNames: [] };
    }

    const blockers = detectBlockers(optionsObj, sourceFile);
    const unsupportedInjectAnalysis = analyzeUnsupportedInjectEntries(optionsObj);

    if (blockers.includes('render function')) {
        return { script: '', scriptType: 'options', status: 'not-migratable', blockers, publicNames: [] };
    }

    if (blockers.length > 0 || unsupportedInjectAnalysis.reasons.length > 0) {
        return {
            script: buildOptionsApiBackoff(sourceFile),
            scriptType: 'options',
            status: 'partially-migratable',
            blockers: [...blockers, ...unsupportedInjectAnalysis.reasons],
            publicNames: [],
        };
    }

    const { script, publicNames, manualMigrationReasons } = buildCompositionApiScript(optionsObj, componentName, sourceFile, useDataScope);
    return {
        script,
        scriptType: 'setup',
        status: manualMigrationReasons.length > 0 ? 'partially-migratable' : 'fully-migratable',
        blockers: manualMigrationReasons,
        publicNames,
    };
}
