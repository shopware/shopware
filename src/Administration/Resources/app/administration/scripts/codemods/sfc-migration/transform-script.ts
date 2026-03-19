import {
    ObjectLiteralExpression,
    Project,
    ScriptKind,
    SourceFile,
    SyntaxKind,
} from 'ts-morph';

// ---------------------------------------------------------------------------
// Public types
// ---------------------------------------------------------------------------

export type MigrationStatus = 'fully-migratable' | 'partially-migratable' | 'not-migratable';

export interface TransformScriptResult {
    script: string;
    scriptType: 'setup' | 'options';
    status: MigrationStatus;
    blockers: string[];
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

type ComputedProp =
    | { name: string; kind: 'getter'; bodyText: string }
    | { name: string; kind: 'getter-setter'; getterBodyText: string; setterParam: string; setterBodyText: string };

interface WatchProp {
    name: string;
    paramName: string;
    bodyText: string;
}

interface MethodProp {
    name: string;
    /** Full parameter list text including types and defaults */
    paramsText: string;
    bodyText: string;
    isAsync: boolean;
}

interface LifecycleHook {
    hookName: string;
    /** null means "run directly in setup" (i.e. created) */
    compositionName: string | null;
    bodyText: string;
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
 * Extracts the Options API object literal from the register call's second argument.
 */
function findOptionsObject(sourceFile: SourceFile): ObjectLiteralExpression | undefined {
    const call = findRegisterCall(sourceFile);
    if (!call) return undefined;

    const secondArg = call.getArguments()[1];
    return secondArg?.isKind(SyntaxKind.ObjectLiteralExpression)
        ? secondArg.asKindOrThrow(SyntaxKind.ObjectLiteralExpression)
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
 * `inject: ['key1', 'key2']`
 * Returns the string values of the array elements.
 */
function extractInjectKeys(optionsObj: ObjectLiteralExpression): string[] {
    const prop = optionsObj.getProperty('inject');
    if (!prop?.isKind(SyntaxKind.PropertyAssignment)) return [];

    const initializer = prop
        .asKindOrThrow(SyntaxKind.PropertyAssignment)
        .getInitializerIfKind(SyntaxKind.ArrayLiteralExpression);

    return (
        initializer
            ?.getElements()
            .filter((el) => el.isKind(SyntaxKind.StringLiteral))
            .map((el) => el.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue()) ?? []
    );
}

/**
 * `data() { return { key: value, … } }`
 * Returns `{ name, valueText }` for each property of the returned object.
 * `valueText` is the original source text so it can be passed directly to `ref(…)`.
 */
function extractDataProps(optionsObj: ObjectLiteralExpression): DataProp[] {
    const dataProp = optionsObj.getProperty('data');
    if (!dataProp?.isKind(SyntaxKind.MethodDeclaration)) return [];

    const body = dataProp.asKindOrThrow(SyntaxKind.MethodDeclaration).getBody();
    if (!body) return [];

    const returnStmt = body.getDescendantsOfKind(SyntaxKind.ReturnStatement)[0];
    const returnExpr = returnStmt?.getExpression();
    if (!returnExpr?.isKind(SyntaxKind.ObjectLiteralExpression)) return [];

    return returnExpr
        .asKindOrThrow(SyntaxKind.ObjectLiteralExpression)
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
            }
        }
    }

    return result;
}

/**
 * `watch: { propName(newVal) {…} }`
 * Each watcher is a shorthand method declaration.
 */
function extractWatchProps(optionsObj: ObjectLiteralExpression): WatchProp[] {
    const watchProp = optionsObj.getProperty('watch');
    if (!watchProp?.isKind(SyntaxKind.PropertyAssignment)) return [];

    const watchObj = watchProp
        .asKindOrThrow(SyntaxKind.PropertyAssignment)
        .getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);
    if (!watchObj) return [];

    return watchObj
        .getProperties()
        .filter((p) => p.isKind(SyntaxKind.MethodDeclaration))
        .map((p) => p.asKindOrThrow(SyntaxKind.MethodDeclaration))
        .map((method) => ({
            name: method.getName(),
            paramName: method.getParameters()[0]?.getName() ?? '',
            bodyText: method.getBodyText() ?? '',
        }));
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

    return methodsObj
        .getProperties()
        .filter((p) => p.isKind(SyntaxKind.MethodDeclaration))
        .map((p) => p.asKindOrThrow(SyntaxKind.MethodDeclaration))
        .map((method) => ({
            name: method.getName(),
            paramsText: method
                .getParameters()
                .map((p) => p.getText())
                .join(', '),
            bodyText: method.getBodyText() ?? '',
            isAsync: method.isAsync(),
        }));
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

/**
 * Returns the list of event names from `emits: ['event1', 'event2']`.
 * Returns an empty array when the property is absent.
 */
function extractEmitsKeys(optionsObj: ObjectLiteralExpression): string[] {
    const prop = optionsObj.getProperty('emits');
    if (!prop?.isKind(SyntaxKind.PropertyAssignment)) return [];

    const initializer = prop
        .asKindOrThrow(SyntaxKind.PropertyAssignment)
        .getInitializerIfKind(SyntaxKind.ArrayLiteralExpression);

    return (
        initializer
            ?.getElements()
            .filter((el) => el.isKind(SyntaxKind.StringLiteral))
            .map((el) => el.asKindOrThrow(SyntaxKind.StringLiteral).getLiteralValue()) ?? []
    );
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
 * Scans a list of code snippets for `this.$refs.NAME` patterns and returns the
 * unique ref names. These need a `const NAME = ref(null)` declaration in setup.
 */
function collectThisRefNames(bodies: string[]): string[] {
    const names = new Set<string>();
    const RE = /\bthis\.\$refs\.(\w+)/g;

    for (const body of bodies) {
        let match: RegExpExecArray | null;
        RE.lastIndex = 0;
        while ((match = RE.exec(body)) !== null) {
            names.add(match[1]);
        }
    }

    return [...names];
}

/**
 * Inspects a list of code snippets and reports which Vue Router / I18n / DOM
 * composables are needed (based on `this.$xxx` patterns found).
 */
function detectUsedComposables(bodies: string[]): UsedComposables {
    const combined = bodies.join('\n');
    return {
        needsRouter: /\bthis\.\$router\b/.test(combined),
        needsRoute: /\bthis\.\$route\b/.test(combined),
        needsNextTick: /\bthis\.\$nextTick\b/.test(combined),
        needsSlots: /\bthis\.\$slots\b/.test(combined),
        needsI18n: /\bthis\.\$tc\b|\bthis\.\$t\b/.test(combined),
        needsEmit: /\bthis\.\$emit\b/.test(combined),
    };
}

/**
 * Scans method bodies for `this.$emit('eventName', …)` patterns and returns the
 * unique event name strings. Used to auto-populate `defineEmits` when the
 * Options API component did not declare an explicit `emits: […]` array.
 */
function collectEmittedEventNames(bodies: string[]): string[] {
    const names = new Set<string>();
    const RE = /\bthis\.\$emit\(\s*['"]([^'"]+)['"]/g;

    for (const body of bodies) {
        let match: RegExpExecArray | null;
        RE.lastIndex = 0;
        while ((match = RE.exec(body)) !== null) {
            names.add(match[1]);
        }
    }

    return [...names];
}

/**
 * Rewrites `this.xxx` references in a method/computed/watch/lifecycle body so
 * they are valid in a `<script setup>` Composition API context.
 *
 * Replacement order matters: special `$`-prefixed properties are handled first
 * to avoid conflicts with the named lookup loop that follows.
 */
function rewriteThisInBody(bodyText: string, ctx: RewriteContext): string {
    let result = bodyText;

    // `this.$refs.NAME` → `NAME.value`  (must come before generic $refs handling)
    result = result.replace(/\bthis\.\$refs\.(\w+)/g, (_, name) => `${name}.value`);

    // Special Vue instance properties
    result = result.replace(/\bthis\.\$emit\b/g, 'emit');
    result = result.replace(/\bthis\.\$router\b/g, 'router');
    result = result.replace(/\bthis\.\$route\b/g, 'route');
    result = result.replace(/\bthis\.\$nextTick\b/g, 'nextTick');
    result = result.replace(/\bthis\.\$slots\b/g, 'slots');
    result = result.replace(/\bthis\.\$props\b/g, 'props');
    result = result.replace(/\bthis\.\$tc\b/g, 'tc');
    result = result.replace(/\bthis\.\$t\b/g, 't');
    // `this.$el` has no clean composition-API equivalent; mark for manual follow-up
    result = result.replace(/\bthis\.\$el\b/g, '/* TODO: $el */ getCurrentInstance()?.proxy?.$el');

    // Named props → `props.NAME`
    for (const name of ctx.propNames) {
        result = result.replace(new RegExp(`\\bthis\\.${escapeRegExp(name)}\\b`, 'g'), `props.${name}`);
    }

    // Named data refs → `NAME.value`
    for (const name of ctx.dataNames) {
        result = result.replace(new RegExp(`\\bthis\\.${escapeRegExp(name)}\\b`, 'g'), `${name}.value`);
    }

    // Named computed refs → `NAME.value`
    for (const name of ctx.computedNames) {
        result = result.replace(new RegExp(`\\bthis\\.${escapeRegExp(name)}\\b`, 'g'), `${name}.value`);
    }

    // Named methods → plain `NAME` (no `.value`)
    for (const name of ctx.methodNames) {
        result = result.replace(new RegExp(`\\bthis\\.${escapeRegExp(name)}\\b`, 'g'), name);
    }

    // Inject keys → plain `NAME` (they are regular constants in Composition API)
    for (const name of ctx.injectNames) {
        result = result.replace(new RegExp(`\\bthis\\.${escapeRegExp(name)}\\b`, 'g'), name);
    }

    return result;
}

function escapeRegExp(s: string): string {
    return s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Detects Options API features that block full Composition API migration.
 * Hard blockers (render) prevent any SFC output; soft blockers (mixins, extends)
 * trigger an Options API backoff instead.
 */
function detectBlockers(optionsObj: ObjectLiteralExpression, sourceFile: SourceFile): string[] {
    const blockers: string[] = [];

    const isExtend = /Shopware\.Component\.extend/.test(
        findRegisterCall(sourceFile)?.getExpression().getText() ?? '',
    );
    if (isExtend) blockers.push('extends');
    if (optionsObj.getProperty('mixins')) blockers.push('mixins');
    if (optionsObj.getProperty('render')) blockers.push('render function');

    return blockers;
}

// ---------------------------------------------------------------------------
// Code generators
// ---------------------------------------------------------------------------

function buildCompositionApiScript(optionsObj: ObjectLiteralExpression, componentName: string, sourceFile: SourceFile): string {
    const injectKeys = extractInjectKeys(optionsObj);
    const dataProps = extractDataProps(optionsObj);
    const computedProps = extractComputedProps(optionsObj);
    const watchProps = extractWatchProps(optionsObj);
    const methodProps = extractMethodProps(optionsObj);
    const lifecycleHooks = extractLifecycleHooks(optionsObj);
    const propsText = extractPropsText(optionsObj);
    const emitsKeys = extractEmitsKeys(optionsObj);
    const inheritAttrs = extractInheritAttrs(optionsObj);
    const moduleLevelCode = extractModuleLevelCode(sourceFile);

    const propNames = new Set(propsText ? extractPropNamesFromText(optionsObj) : []);
    const dataNames = new Set(dataProps.map((p) => p.name));
    const computedNames = new Set(computedProps.map((p) => p.name));
    const methodNames = new Set(methodProps.map((p) => p.name));
    const injectNames = new Set(injectKeys);

    const ctx: RewriteContext = { propNames, dataNames, computedNames, methodNames, injectNames };

    // Collect all body texts to scan for composable usage and template refs
    const allBodies = [
        ...dataProps.map((p) => p.valueText),
        ...computedProps.map((p) =>
            p.kind === 'getter' ? p.bodyText : p.getterBodyText + '\n' + p.setterBodyText,
        ),
        ...watchProps.map((p) => p.bodyText),
        ...methodProps.map((p) => p.bodyText),
        ...lifecycleHooks.map((h) => h.bodyText),
    ];

    const usedComposables = detectUsedComposables(allBodies);
    const templateRefNames = collectThisRefNames(allBodies);

    // Determine the final emits list: prefer explicit `emits: [...]`, fall back to
    // scanning method bodies for `this.$emit('eventName', ...)` calls.
    const effectiveEmitsKeys =
        emitsKeys.length > 0 ? emitsKeys : collectEmittedEventNames(allBodies);

    // Determine which Vue composables are actually needed
    const vueImports: string[] = [];
    if (dataProps.length > 0 || templateRefNames.length > 0) vueImports.push('ref');
    if (computedProps.length > 0) vueImports.push('computed');
    if (injectKeys.length > 0) vueImports.push('inject');
    if (watchProps.length > 0) vueImports.push('watch');
    if (usedComposables.needsNextTick) vueImports.push('nextTick');
    if (usedComposables.needsSlots) vueImports.push('useSlots');
    // Check whether we need getCurrentInstance for $el handling
    const needsGetCurrentInstance = allBodies.some((b) => /\bthis\.\$el\b/.test(b));
    if (needsGetCurrentInstance) vueImports.push('getCurrentInstance');

    const regularHooks = lifecycleHooks.filter((h) => h.compositionName !== null);
    vueImports.push(...new Set(regularHooks.map((h) => h.compositionName as string)));

    // All names exposed to the template via the `public:` return + top-level destructuring.
    // Template refs are declared outside createExtendableSetup as module-level refs so they
    // do not need to be in publicNames — the template can access them directly.
    const publicNames = [
        ...injectKeys,
        ...dataProps.map((p) => p.name),
        ...computedProps.map((p) => p.name),
        ...methodProps.map((p) => p.name),
    ];

    const lines: string[] = [];

    // ── module-level code (scss imports, cloneDeep, colors, etc.) ────────────
    if (moduleLevelCode) {
        lines.push(moduleLevelCode);
        lines.push('');
    }

    // ── Vue compiler macros (defineOptions, defineProps, defineEmits) ─────────
    if (!inheritAttrs) {
        lines.push(`defineOptions({ inheritAttrs: false });`);
        lines.push('');
    }

    if (propsText) {
        lines.push(`const props = defineProps(${propsText});`);
    } else {
        lines.push(`const props = defineProps({});`);
    }

    if (effectiveEmitsKeys.length > 0) {
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
    if (usedComposables.needsI18n) lines.push(`const { t, tc } = useI18n();`);
    const hasComposableDeclarations =
        usedComposables.needsRouter ||
        usedComposables.needsRoute ||
        usedComposables.needsSlots ||
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
    injectKeys.forEach((key) => {
        lines.push(`        const ${key} = inject('${key}');`);
    });
    if (injectKeys.length > 0) lines.push('');

    // ── data → ref() ─────────────────────────────────────────────────────────
    dataProps.forEach(({ name, valueText }) => {
        // Data initializers may reference props via `this.propName` — rewrite those
        const rewrittenValue = rewriteThisInBody(valueText, ctx);
        lines.push(`        const ${name} = ref(${rewrittenValue});`);
    });
    if (dataProps.length > 0) lines.push('');

    // ── computed ──────────────────────────────────────────────────────────────
    computedProps.forEach((prop) => {
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
    if (computedProps.length > 0) lines.push('');

    // ── watch ─────────────────────────────────────────────────────────────────
    watchProps.forEach(({ name, paramName, bodyText }) => {
        // Use `props.name` for watched props, `name.value` for watched data/computed refs
        const source = propNames.has(name) ? `props.${name}` : `${name}.value`;
        const body = rewriteThisInBody(bodyText, ctx);
        const paramPart = paramName ? `(${paramName}) => {` : `() => {`;
        lines.push(`        watch(() => ${source}, ${paramPart}`);
        lines.push(indentBlock(body, 12));
        lines.push(`        });`);
    });
    if (watchProps.length > 0) lines.push('');

    // ── methods ───────────────────────────────────────────────────────────────
    methodProps.forEach(({ name, paramsText, bodyText, isAsync }) => {
        const asyncKw = isAsync ? 'async ' : '';
        const body = rewriteThisInBody(bodyText, ctx);
        lines.push(`        const ${name} = ${asyncKw}(${paramsText}) => {`);
        lines.push(indentBlock(body, 12));
        lines.push(`        };`);
    });
    if (methodProps.length > 0) lines.push('');

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

    return lines.join('\n');
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

    const initializer = prop
        .asKindOrThrow(SyntaxKind.PropertyAssignment)
        .getInitializerIfKind(SyntaxKind.ObjectLiteralExpression);

    return (
        initializer
            ?.getProperties()
            .filter(
                (p) =>
                    p.isKind(SyntaxKind.PropertyAssignment) || p.isKind(SyntaxKind.MethodDeclaration),
            )
            .map((p) => {
                if (p.isKind(SyntaxKind.PropertyAssignment)) {
                    return p.asKindOrThrow(SyntaxKind.PropertyAssignment).getName();
                }
                return p.asKindOrThrow(SyntaxKind.MethodDeclaration).getName();
            }) ?? []
    );
}

/**
 * Preserves the original Options API source, removing only the template import
 * (which is replaced by the `<template>` section in the SFC).
 */
function buildOptionsApiBackoff(sourceFile: SourceFile): string {
    // Use ts-morph to find and remove only the `import template from '…'` declaration
    const templateImport = sourceFile
        .getImportDeclarations()
        .find((imp) => imp.getDefaultImport()?.getText() === 'template');

    templateImport?.remove();

    return sourceFile.getFullText().trim();
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
 * All JS analysis is done via the TypeScript compiler's AST (through ts-morph)
 * to handle edge cases that regex cannot: template literals, brace-in-strings,
 * comments, default parameters, async methods, etc.
 */
export function transformScript(jsContent: string): TransformScriptResult {
    const sourceFile = parseSource(jsContent);
    const optionsObj = findOptionsObject(sourceFile);
    const componentName = extractComponentName(sourceFile);

    if (!optionsObj) {
        return { script: '', scriptType: 'options', status: 'not-migratable', blockers: ['no options object found'] };
    }

    const blockers = detectBlockers(optionsObj, sourceFile);

    if (blockers.includes('render function')) {
        return { script: '', scriptType: 'options', status: 'not-migratable', blockers };
    }

    if (blockers.length > 0) {
        return {
            script: buildOptionsApiBackoff(sourceFile),
            scriptType: 'options',
            status: 'partially-migratable',
            blockers,
        };
    }

    return {
        script: buildCompositionApiScript(optionsObj, componentName, sourceFile),
        scriptType: 'setup',
        status: 'fully-migratable',
        blockers: [],
    };
}
