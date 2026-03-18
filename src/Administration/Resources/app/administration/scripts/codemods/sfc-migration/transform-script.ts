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

const LIFECYCLE_MAP: Record<string, string> = {
    mounted: 'onMounted',
    // `created` has no direct equivalent; onMounted is the closest approximation
    created: 'onMounted',
    beforeMount: 'onBeforeMount',
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
    compositionName: string;
    bodyText: string;
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
 */
function extractLifecycleHooks(optionsObj: ObjectLiteralExpression): LifecycleHook[] {
    const result: LifecycleHook[] = [];

    for (const prop of optionsObj.getProperties()) {
        if (!prop.isKind(SyntaxKind.MethodDeclaration)) continue;

        const method = prop.asKindOrThrow(SyntaxKind.MethodDeclaration);
        const hookName = method.getName();
        const compositionName = LIFECYCLE_MAP[hookName];

        if (compositionName) {
            result.push({ hookName, compositionName, bodyText: method.getBodyText() ?? '' });
        }
    }

    return result;
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

function buildCompositionApiScript(optionsObj: ObjectLiteralExpression, componentName: string): string {
    const injectKeys = extractInjectKeys(optionsObj);
    const dataProps = extractDataProps(optionsObj);
    const computedProps = extractComputedProps(optionsObj);
    const watchProps = extractWatchProps(optionsObj);
    const methodProps = extractMethodProps(optionsObj);
    const lifecycleHooks = extractLifecycleHooks(optionsObj);

    // Determine which Vue composables are actually needed
    const vueImports: string[] = [];
    if (dataProps.length > 0) vueImports.push('ref');
    if (computedProps.length > 0) vueImports.push('computed');
    if (injectKeys.length > 0) vueImports.push('inject');
    if (watchProps.length > 0) vueImports.push('watch');
    vueImports.push(...new Set(lifecycleHooks.map((h) => h.compositionName)));

    // All names exposed to the template via the `public:` return + top-level destructuring
    const publicNames = [
        ...injectKeys,
        ...dataProps.map((p) => p.name),
        ...computedProps.map((p) => p.name),
        ...methodProps.map((p) => p.name),
    ];

    const lines: string[] = [];

    // ── imports ──────────────────────────────────────────────────────────────
    lines.push(`import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';`);
    if (vueImports.length > 0) {
        lines.push(`import { ${vueImports.join(', ')} } from 'vue';`);
    }
    lines.push('');

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
        lines.push(`        const ${name} = ref(${valueText});`);
    });
    if (dataProps.length > 0) lines.push('');

    // ── computed ──────────────────────────────────────────────────────────────
    computedProps.forEach((prop) => {
        if (prop.kind === 'getter') {
            lines.push(`        const ${prop.name} = computed(() => {${prop.bodyText}});`);
        } else {
            lines.push(`        const ${prop.name} = computed({`);
            lines.push(`            get: () => {${prop.getterBodyText}},`);
            lines.push(`            set: (${prop.setterParam}) => {${prop.setterBodyText}},`);
            lines.push('        });');
        }
    });
    if (computedProps.length > 0) lines.push('');

    // ── watch ─────────────────────────────────────────────────────────────────
    watchProps.forEach(({ name, paramName, bodyText }) => {
        lines.push(`        watch(() => ${name}.value, (${paramName}) => {${bodyText}});`);
    });
    if (watchProps.length > 0) lines.push('');

    // ── methods ───────────────────────────────────────────────────────────────
    methodProps.forEach(({ name, paramsText, bodyText, isAsync }) => {
        const asyncKw = isAsync ? 'async ' : '';
        lines.push(`        const ${name} = ${asyncKw}(${paramsText}) => {${bodyText}};`);
    });
    if (methodProps.length > 0) lines.push('');

    // ── lifecycle hooks ───────────────────────────────────────────────────────
    lifecycleHooks.forEach(({ compositionName, hookName, bodyText }) => {
        if (hookName === 'created') {
            lines.push(`        // NOTE: 'created' has no direct Composition API equivalent — mapped to onMounted`);
        }
        lines.push(`        ${compositionName}(() => {${bodyText}});`);
    });
    if (lifecycleHooks.length > 0) lines.push('');

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
        script: buildCompositionApiScript(optionsObj, componentName),
        scriptType: 'setup',
        status: 'fully-migratable',
        blockers: [],
    };
}
