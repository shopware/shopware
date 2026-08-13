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
import { resolveIdentifierNames } from './resolve-identifiers';
import type { ResolvedIdentifiers } from './resolve-identifiers';
import { buildWatchSource, rewriteThisInBody } from './rewrite-this';

export function emitCompositionApiScript(state: CompositionScriptState): string {
    // Every name the component already uses is known before the first line is
    // emitted, so the names of the generated bindings are resolved once here.
    const names = resolveIdentifierNames(collectTakenNames(state));

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
        emitCreatedHooks(state, names),
        emitRegularHooks(state, names),
        emitSwDefinePublic(state),
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
    if (usedComposables.needsI18n) {
        lines.push(`import { useI18n } from 'vue-i18n';`);
    }

    return lines;
}

function emitComposableDeclarations(state: CompositionScriptState, names: ResolvedIdentifiers): string[] {
    const { usedComposables } = state;
    const lines: string[] = [];

    if (usedComposables.needsRouter) lines.push(`const ${names.router} = useRouter();`);
    if (usedComposables.needsRoute) lines.push(`const ${names.route} = useRoute();`);
    if (usedComposables.needsSlots) lines.push(`const ${names.slots} = useSlots();`);
    if (usedComposables.needsAttrs) lines.push(`const ${names.attrs} = useAttrs();`);
    // `useI18n()` returns an object, so a renamed `t` needs a destructuring alias.
    if (usedComposables.needsI18n) {
        lines.push(names.t === 't' ? 'const { t } = useI18n();' : `const { t: ${names.t} } = useI18n();`);
    }

    return lines;
}

function emitTemplateRefs(state: CompositionScriptState): string[] {
    return state.templateRefNames.map((refName) => `const ${refName} = ref(null);`);
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
        if (prop.kind === 'getter') {
            const body = rewriteThisInBody(prop.bodyText, ctx, names);

            return `const ${prop.name} = computed(() => {\n${body}\n});`;
        }

        const getterBody = rewriteThisInBody(prop.getterBodyText, ctx, names);
        const setterBody = rewriteThisInBody(prop.setterBodyText, ctx, names);

        return `const ${prop.name} = computed({\nget: () => {\n${getterBody}\n},\nset: (${prop.setterParam}) => {\n${setterBody}\n},\n});`;
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

function collectTakenNames(state: CompositionScriptState): Set<string> {
    // Declared prop names count as taken: the extension runtime strips them from
    // the returned setup state, so a generated binding that shadows a prop would
    // be dropped and leave the template reading `undefined`.
    return new Set([
        ...state.existingBindingNames,
        ...state.publicNames,
        ...state.templateRefNames,
        ...state.propNames,
        'props',
    ]);
}
