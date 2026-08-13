/**
 * Emits the generated component as native `<script setup>` code. The build-time
 * transform in `build/vue-setup-transform` lowers it into the extension runtime,
 * so no `createExtendableSetup()` wrapper is written here.
 *
 * Emitters only produce valid code, never layout: `generate-sfc.ts` runs the
 * assembled SFC through prettier, so indentation inside a line does not matter.
 * Blank lines do — prettier keeps them, and they are what groups the output.
 *
 * See `technical-docs/03-extensibility/07-native-setup-authoring.md`.
 */
import { quoteJsString } from '../string-literals';
import { hasTopLevelReturn } from './ast';
import type { CompositionScriptState } from './composition-script-state';
import { sanitizeTodoCommentText } from './helpers';
import type { ResolvedIdentifiers } from './resolve-identifiers';
import { buildWatchSource, rewriteThisInBody } from './rewrite-this';
import type { UsedComposables } from './types';

export function emitCompositionApiScript(state: CompositionScriptState): string {
    // Resolved while the state was collected: `transform-script.ts` has to report
    // the root template ref name back to the template transformer, so the names
    // cannot be picked here for the first time.
    const { names } = state;

    // Each emitter returns one section; sections that emitted something are
    // separated by exactly one blank line, which is what groups the generated
    // code. A section that emitted a single empty line keeps it.
    const sections = [
        emitTodoComments(state),
        emitModuleLevelCode(state),
        emitDefineOptions(state),
        emitMacroDeclarations(state, names),
        emitImports(state),
        emitComposableDeclarations(state, names),
        emitTemplateRefs(state),
        emitInjectProps(state),
        emitDataProps(state, names),
        emitComputedProps(state, names),
        emitMethodProps(state, names),
        emitUnsupportedWatchEntries(state),
        emitWatchProps(state, names),
        emitProvideEntries(state, names),
        emitCreatedHooks(state, names),
        emitRegularHooks(state, names),
        emitSwDefinePublic(state),
        emitDefineExpose(state),
    ];

    return sections
        .filter((section) => section.length > 0)
        .map((section) => section.join('\n'))
        .join('\n\n');
}

function emitTodoComments(state: CompositionScriptState): string[] {
    const { todoComments } = state;

    return todoComments.length > 0 ? [todoComments.join('\n')] : [];
}

function emitModuleLevelCode(state: CompositionScriptState): string[] {
    const { moduleLevelCode } = state;

    return moduleLevelCode ? [moduleLevelCode] : [];
}

function emitDefineOptions(state: CompositionScriptState): string[] {
    const { componentNameValue, inheritAttrs } = state;
    const defineOptionsArgs = [
        !inheritAttrs ? 'inheritAttrs: false' : '',
        componentNameValue ? `name: ${componentNameValue}` : '',
    ].filter(Boolean);

    return defineOptionsArgs.length > 0 ? [`defineOptions({ ${defineOptionsArgs.join(', ')} });`] : [];
}

function emitMacroDeclarations(state: CompositionScriptState, names: ResolvedIdentifiers): string[] {
    const { effectiveEmitsKeys, emitsDefinition, propsText, usedComposables } = state;
    const lines: string[] = [];

    // An empty defineProps({}) would only add an unused `props` binding to the
    // component state, so the macro is emitted for real props definitions only.
    if (propsText) {
        lines.push(`const props = defineProps(${propsText});`);
    }

    if (emitsDefinition.objectText !== null) {
        lines.push(`const ${names.emit} = defineEmits(${emitsDefinition.objectText});`);
    } else if (effectiveEmitsKeys.length > 0) {
        const emitsList = effectiveEmitsKeys.map((k) => `'${k}'`).join(', ');
        lines.push(`const ${names.emit} = defineEmits([${emitsList}]);`);
    } else if (usedComposables.needsEmit) {
        lines.push(`const ${names.emit} = defineEmits([]);`);
    }

    return lines;
}

function emitImports(state: CompositionScriptState): string[] {
    const { usedComposables, vueImports } = state;
    const lines: string[] = [];

    if (vueImports.length > 0) {
        lines.push(`import { ${[...new Set(vueImports)].join(', ')} } from 'vue';`);
    }

    const routerImports: string[] = [];
    if (usedComposables.needsRouter) routerImports.push('useRouter');
    if (usedComposables.needsRoute) routerImports.push('useRoute');
    if (routerImports.length > 0) {
        lines.push(`import { ${routerImports.join(', ')} } from 'vue-router';`);
    }
    if (usesI18n(usedComposables)) {
        lines.push(`import { useI18n } from 'vue-i18n';`);
    }

    return lines;
}

function usesI18n(usedComposables: UsedComposables): boolean {
    return usedComposables.needsTranslate || usedComposables.needsTranslationExists;
}

function emitComposableDeclarations(state: CompositionScriptState, names: ResolvedIdentifiers): string[] {
    const { usedComposables } = state;
    const lines: string[] = [];

    if (usedComposables.needsRouter) lines.push(`const ${names.router} = useRouter();`);
    if (usedComposables.needsRoute) lines.push(`const ${names.route} = useRoute();`);
    if (usedComposables.needsSlots) lines.push(`const ${names.slots} = useSlots();`);
    if (usedComposables.needsAttrs) lines.push(`const ${names.attrs} = useAttrs();`);
    // `$device` is a global property the device-helper plugin installs, and its
    // DeviceHelper singleton is closed over inside `install()` — there is nothing
    // to import. Reading it once here is equivalent: `getCurrentInstance()` is
    // non-null in the setup body, and the singleton never changes.
    if (usedComposables.needsDevice) lines.push(`const ${names.device} = getCurrentInstance()?.proxy?.$device;`);
    // `useI18n()` returns one composer object, so `t` and `te` are destructured
    // from a single call, each with an alias when the component took the name.
    if (usesI18n(usedComposables)) {
        const members = [
            usedComposables.needsTranslate ? destructuringEntry('t', names.t) : '',
            usedComposables.needsTranslationExists ? destructuringEntry('te', names.te) : '',
        ].filter(Boolean);

        lines.push(`const { ${members.join(', ')} } = useI18n();`);
    }

    return lines;
}

function destructuringEntry(member: string, localName: string): string {
    return member === localName ? member : `${member}: ${localName}`;
}

function emitTemplateRefs(state: CompositionScriptState): string[] {
    const { rootElementRefName, templateRefNames } = state;
    // The root ref is not a `$refs` name the component wrote; the template
    // transformer puts the matching `ref="…"` on the element for it.
    const rootElementRef = rootElementRefName ? [`const ${rootElementRefName} = ref(null);`] : [];

    return [
        ...rootElementRef,
        ...templateRefNames.map((refName) => `const ${refName} = ref(null);`),
    ];
}

function emitInjectProps(state: CompositionScriptState): string[] {
    const { supportedInjectProps } = state;

    return supportedInjectProps.map(({ localName, sourceKey, defaultValueText, treatDefaultAsFactory }) => {
        const args = [quoteJsString(sourceKey)];

        if (defaultValueText !== undefined) {
            args.push(defaultValueText);

            if (treatDefaultAsFactory) {
                args.push('true');
            }
        }

        return `const ${localName} = inject(${args.join(', ')});`;
    });
}

function emitDataProps(state: CompositionScriptState, names: ResolvedIdentifiers): string[] {
    const { ctx, supportedDataProps } = state;

    return supportedDataProps.map(({ name, valueText }) => {
        const rewrittenValue = rewriteThisInBody(valueText, ctx, names, 'expression');

        return `const ${name} = ref(${rewrittenValue});`;
    });
}

function emitComputedProps(state: CompositionScriptState, names: ResolvedIdentifiers): string[] {
    const { ctx, supportedComputedProps } = state;

    return supportedComputedProps.map((prop) => {
        // An expanded entry has no counterpart in the source, so the comment
        // names where it came from.
        const commentLine = prop.comment ? `// ${sanitizeTodoCommentText(prop.comment)}\n` : '';

        if (prop.kind === 'getter') {
            const body = rewriteThisInBody(prop.bodyText, ctx, names);

            return `${commentLine}const ${prop.name} = computed(() => {\n${body}\n});`;
        }

        const getterBody = rewriteThisInBody(prop.getterBodyText, ctx, names);
        const setterBody = rewriteThisInBody(prop.setterBodyText, ctx, names);

        return `${commentLine}const ${prop.name} = computed({\nget: () => {\n${getterBody}\n},\nset: (${prop.setterParam}) => {\n${setterBody}\n},\n});`;
    });
}

function emitMethodProps(state: CompositionScriptState, names: ResolvedIdentifiers): string[] {
    const { ctx, supportedMethodProps } = state;

    return supportedMethodProps.map(({ name, paramsText, bodyText, isAsync, rawText }) => {
        if (rawText !== undefined) {
            // Property-assignment methods often wrap callbacks in helpers such
            // as debounce(). Preserve the wrapper expression instead of
            // flattening it into a plain arrow method.
            const normalizedRawText = rawText.replace(/\bfunction\s+\w*\s*\(([^)]*)\)\s*\{/g, '($1) => {');
            const rewritten = rewriteThisInBody(normalizedRawText, ctx, names, 'expression');

            return `const ${name} = ${rewritten};`;
        }

        const asyncKw = isAsync ? 'async ' : '';
        const body = rewriteThisInBody(bodyText, ctx, names);

        return `const ${name} = ${asyncKw}(${paramsText}) => {\n${body}\n};`;
    });
}

/**
 * Emitted after the watchers and before `created`, which is where the Options
 * API evaluates `provide()`: `applyOptions` runs the watch options first and the
 * `created` hook after, so an `immediate` watcher must already have run when the
 * provided values are read. The slot is also past every `const` a provided value
 * can reference, so nothing is read inside its temporal dead zone.
 */
function emitProvideEntries(state: CompositionScriptState, names: ResolvedIdentifiers): string[] {
    const { ctx, provideEntries } = state;

    return provideEntries.map(
        ({ key, valueText }) => `provide(${quoteJsString(key)}, ${rewriteThisInBody(valueText, ctx, names, 'expression')});`,
    );
}

function emitUnsupportedWatchEntries(state: CompositionScriptState): string[] {
    return state.unsupportedWatchEntries.map(
        (entry) => `// TODO: migrate watch entry manually: ${sanitizeTodoCommentText(entry)}`,
    );
}

function emitWatchProps(state: CompositionScriptState, names: ResolvedIdentifiers): string[] {
    const { ctx, injectNames, propNames, supportedWatchProps } = state;

    return supportedWatchProps.map(({ name, paramsText, bodyText, handlerName, isAsync, deep, immediate }) => {
        const source = buildWatchSource(name, propNames, injectNames, names);
        const optionsParts = [
            deep ? 'deep: true' : '',
            immediate ? 'immediate: true' : '',
        ].filter(Boolean);

        if (handlerName) {
            return `watch(() => ${source}, (...args) => ${handlerName}(...args)${optionsParts.length > 0 ? `, { ${optionsParts.join(', ')} }` : ''});`;
        }

        const body = rewriteThisInBody(bodyText ?? '', ctx, names);
        const asyncPrefix = isAsync ? 'async ' : '';
        const paramPart = paramsText ? `${asyncPrefix}(${paramsText}) => {` : `${asyncPrefix}() => {`;
        const closing = optionsParts.length > 0 ? `}, { ${optionsParts.join(', ')} });` : '});';

        return `watch(() => ${source}, ${paramPart}\n${body}\n${closing}`;
    });
}

function emitCreatedHooks(state: CompositionScriptState, names: ResolvedIdentifiers): string[] {
    const { ctx, lifecycleHooks } = state;
    const createdHooks = lifecycleHooks.filter((h) => h.compositionName === null);

    // created() has no Composition API hook. Running it directly inside
    // setup preserves its pre-mount timing; async created() stays
    // fire-and-forget so setup itself does not become async.
    return createdHooks.map((hook) => {
        const body = rewriteThisInBody(hook.bodyText.trim(), ctx, names);

        if (hook.isAsync) {
            return `void (async () => {\n${body}\n})();`;
        }

        if (hasTopLevelReturn(hook.bodyText)) {
            // A guard clause like `if (…) { return; }` is only legal inside a
            // function, and the setup body is module-level code.
            return `(() => {\n${body}\n})();`;
        }

        return body;
    });
}

function emitRegularHooks(state: CompositionScriptState, names: ResolvedIdentifiers): string[] {
    const { ctx, regularHooks } = state;

    return regularHooks.map(({ compositionName, bodyText, isAsync }) => {
        const body = rewriteThisInBody(bodyText, ctx, names);
        const asyncPrefix = isAsync ? 'async ' : '';

        return `${compositionName}(${asyncPrefix}() => {\n${body}\n});`;
    });
}

function emitSwDefinePublic(state: CompositionScriptState): string[] {
    const { publicNames } = state;

    // Base mode is auto-private: every top-level binding stays component state,
    // but only the names listed here form the public override API. The marker is
    // mandatory, so an empty object is emitted when nothing is public.
    if (publicNames.length === 0) {
        return ['swDefinePublic({});'];
    }

    return [
        'swDefinePublic({',
        ...publicNames.map((n) => `    ${n},`),
        '});',
    ];
}

/**
 * Emitted last, after the `swDefinePublic({ … })` marker. The two markers declare
 * the component's two public surfaces — the override API and the API a parent
 * reaches through a template ref — so they belong together, and the end of the
 * block is the only slot that is always past every binding the object reads.
 *
 * A `<script setup>` component is closed by default, so without this list a
 * parent's template ref would find nothing. The names are the ones the Options
 * API `expose` listed, but the surface is not identical: the base lowering
 * renames every author binding to `__swSetupAuthor_<name>` and leaves the macro
 * argument pointing at that alias, while the template reads the
 * override-resolved binding re-declared in the generated footer. So the object a
 * parent reaches through its template ref carries the base implementation, and a
 * plugin override of an exposed member is not visible there — a platform
 * property of the lowering, not of this codemod (see
 * `build/vue-setup-transform/index.spec/base-macro-constraints.spec.ts`).
 */
function emitDefineExpose(state: CompositionScriptState): string[] {
    const { exposeNames } = state;

    // `expose: []` closes the instance completely in the Options API, which is
    // what script setup already does — nothing to emit and nothing to review.
    if (exposeNames.length === 0) {
        return [];
    }

    // A repeated name in the Options API list is a no-op there, but the same key
    // twice in an object literal is a lint error, so the list is deduped.
    return [
        'defineExpose({',
        ...[...new Set(exposeNames)].map((n) => `    ${n},`),
        '});',
    ];
}
