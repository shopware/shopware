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
    const lines: string[] = [];
    // Every name the component already uses is known before the first line is
    // emitted, so the names of the generated bindings are resolved once here.
    const names = resolveIdentifierNames(collectTakenNames(state));

    emitTodoComments(lines, state);
    emitModuleLevelCode(lines, state);
    emitCompilerMacros(lines, state, names);
    emitImports(lines, state);
    emitComposableDeclarations(lines, state, names);
    emitTemplateRefs(lines, state);
    emitNativeSetup(lines, state, names);

    return lines.join('\n');
}

function emitTodoComments(lines: string[], state: CompositionScriptState): void {
    const { todoComments } = state;

    if (todoComments.length > 0) {
        lines.push(todoComments.join('\n'));
        lines.push('');
    }
}

function emitModuleLevelCode(lines: string[], state: CompositionScriptState): void {
    const { moduleLevelCode } = state;

    if (moduleLevelCode) {
        lines.push(moduleLevelCode);
        lines.push('');
    }
}

function emitCompilerMacros(lines: string[], state: CompositionScriptState, names: ResolvedIdentifiers): void {
    const { componentNameValue, effectiveEmitsKeys, emitsDefinition, inheritAttrs, propsText, usedComposables } = state;
    const defineOptionsArgs = [
        !inheritAttrs ? 'inheritAttrs: false' : '',
        componentNameValue ? `name: ${componentNameValue}` : '',
    ].filter(Boolean);
    if (defineOptionsArgs.length > 0) {
        lines.push(`defineOptions({ ${defineOptionsArgs.join(', ')} });`);
        lines.push('');
    }

    const declarationLines: string[] = [];

    // An empty defineProps({}) would only add an unused `props` binding to the
    // component state, so the macro is emitted for real props definitions only.
    if (propsText) {
        declarationLines.push(`const props = defineProps(${propsText});`);
    }

    if (emitsDefinition.objectText !== null) {
        declarationLines.push(`const ${names.emit} = defineEmits(${emitsDefinition.objectText});`);
    } else if (effectiveEmitsKeys.length > 0) {
        const emitsList = effectiveEmitsKeys.map((k) => `'${k}'`).join(', ');
        declarationLines.push(`const ${names.emit} = defineEmits([${emitsList}]);`);
    } else if (usedComposables.needsEmit) {
        declarationLines.push(`const ${names.emit} = defineEmits([]);`);
    }

    if (declarationLines.length === 0) {
        return;
    }

    lines.push(...declarationLines);
    lines.push('');
}

function emitImports(lines: string[], state: CompositionScriptState): void {
    const { usedComposables, vueImports } = state;

    const importLines: string[] = [];

    if (vueImports.length > 0) {
        importLines.push(`import { ${[...new Set(vueImports)].join(', ')} } from 'vue';`);
    }

    const routerImports: string[] = [];
    if (usedComposables.needsRouter) routerImports.push('useRouter');
    if (usedComposables.needsRoute) routerImports.push('useRoute');
    if (routerImports.length > 0) {
        importLines.push(`import { ${routerImports.join(', ')} } from 'vue-router';`);
    }
    if (usedComposables.needsI18n) {
        importLines.push(`import { useI18n } from 'vue-i18n';`);
    }

    if (importLines.length === 0) {
        return;
    }

    lines.push(...importLines);
    lines.push('');
}

function emitComposableDeclarations(lines: string[], state: CompositionScriptState, names: ResolvedIdentifiers): void {
    const { usedComposables } = state;

    if (usedComposables.needsRouter) lines.push(`const ${names.router} = useRouter();`);
    if (usedComposables.needsRoute) lines.push(`const ${names.route} = useRoute();`);
    if (usedComposables.needsSlots) lines.push(`const ${names.slots} = useSlots();`);
    if (usedComposables.needsAttrs) lines.push(`const ${names.attrs} = useAttrs();`);
    // `useI18n()` returns an object, so a renamed `t` needs a destructuring alias.
    if (usedComposables.needsI18n) {
        lines.push(names.t === 't' ? 'const { t } = useI18n();' : `const { t: ${names.t} } = useI18n();`);
    }
    const hasComposableDeclarations =
        usedComposables.needsRouter ||
        usedComposables.needsRoute ||
        usedComposables.needsSlots ||
        usedComposables.needsAttrs ||
        usedComposables.needsI18n;
    if (hasComposableDeclarations) {
        lines.push('');
    }
}

function emitTemplateRefs(lines: string[], state: CompositionScriptState): void {
    const { templateRefNames } = state;

    for (const refName of templateRefNames) {
        lines.push(`const ${refName} = ref(null);`);
    }
    if (templateRefNames.length > 0) lines.push('');
}

function emitNativeSetup(lines: string[], state: CompositionScriptState, names: ResolvedIdentifiers): void {
    emitSupportedInjectProps(lines, state);
    emitSupportedDataProps(lines, state, names);
    emitSupportedComputedProps(lines, state, names);
    emitSupportedMethodProps(lines, state, names);
    emitUnsupportedWatchEntries(lines, state);
    emitSupportedWatchProps(lines, state, names);
    emitCreatedHooks(lines, state, names);
    emitRegularHooks(lines, state, names);
    emitSwDefinePublic(lines, state);
}

function emitSupportedInjectProps(lines: string[], state: CompositionScriptState): void {
    const { supportedInjectProps } = state;

    supportedInjectProps.forEach(({ localName, sourceKey, defaultValueText, treatDefaultAsFactory }) => {
        const args = [quoteJsString(sourceKey)];

        if (defaultValueText !== undefined) {
            args.push(defaultValueText);

            if (treatDefaultAsFactory) {
                args.push('true');
            }
        }

        lines.push(`const ${localName} = inject(${args.join(', ')});`);
    });
    if (supportedInjectProps.length > 0) lines.push('');
}

function emitSupportedDataProps(lines: string[], state: CompositionScriptState, names: ResolvedIdentifiers): void {
    const { ctx, supportedDataProps } = state;

    supportedDataProps.forEach(({ name, valueText }) => {
        const rewrittenValue = rewriteThisInBody(valueText, ctx, names, 'expression');
        lines.push(`const ${name} = ref(${rewrittenValue});`);
    });
    if (supportedDataProps.length > 0) lines.push('');
}

function emitSupportedComputedProps(lines: string[], state: CompositionScriptState, names: ResolvedIdentifiers): void {
    const { ctx, supportedComputedProps } = state;

    supportedComputedProps.forEach((prop) => {
        if (prop.kind === 'getter') {
            const body = rewriteThisInBody(prop.bodyText, ctx, names);
            lines.push(`const ${prop.name} = computed(() => {\n${body}\n});`);
        } else {
            const getterBody = rewriteThisInBody(prop.getterBodyText, ctx, names);
            const setterBody = rewriteThisInBody(prop.setterBodyText, ctx, names);
            lines.push(
                `const ${prop.name} = computed({\nget: () => {\n${getterBody}\n},\nset: (${prop.setterParam}) => {\n${setterBody}\n},\n});`,
            );
        }
    });
    if (supportedComputedProps.length > 0) lines.push('');
}

function emitSupportedMethodProps(lines: string[], state: CompositionScriptState, names: ResolvedIdentifiers): void {
    const { ctx, supportedMethodProps } = state;

    supportedMethodProps.forEach(({ name, paramsText, bodyText, isAsync, rawText }) => {
        if (rawText !== undefined) {
            // Property-assignment methods often wrap callbacks in helpers such
            // as debounce(). Preserve the wrapper expression instead of
            // flattening it into a plain arrow method.
            const normalizedRawText = rawText.replace(/\bfunction\s+\w*\s*\(([^)]*)\)\s*\{/g, '($1) => {');
            const rewritten = rewriteThisInBody(normalizedRawText, ctx, names, 'expression');
            lines.push(`const ${name} = ${rewritten};`);
        } else {
            const asyncKw = isAsync ? 'async ' : '';
            const body = rewriteThisInBody(bodyText, ctx, names);
            lines.push(`const ${name} = ${asyncKw}(${paramsText}) => {\n${body}\n};`);
        }
    });
    if (supportedMethodProps.length > 0) lines.push('');
}

function emitUnsupportedWatchEntries(lines: string[], state: CompositionScriptState): void {
    const { unsupportedWatchEntries } = state;

    unsupportedWatchEntries.forEach((entry) => {
        lines.push(`// TODO: migrate watch entry manually: ${sanitizeTodoCommentText(entry)}`);
    });
    if (unsupportedWatchEntries.length > 0) lines.push('');
}

function emitSupportedWatchProps(lines: string[], state: CompositionScriptState, names: ResolvedIdentifiers): void {
    const { ctx, injectNames, propNames, supportedWatchProps } = state;

    supportedWatchProps.forEach(({ name, paramsText, bodyText, handlerName, isAsync, deep, immediate }) => {
        const source = buildWatchSource(name, propNames, injectNames, names);
        const hasOptions = deep || immediate;
        const optionsParts = [
            deep ? 'deep: true' : '',
            immediate ? 'immediate: true' : '',
        ].filter(Boolean);

        if (handlerName) {
            lines.push(
                `watch(() => ${source}, (...args) => ${handlerName}(...args)${hasOptions ? `, { ${optionsParts.join(', ')} }` : ''});`,
            );
            return;
        }

        const body = rewriteThisInBody(bodyText ?? '', ctx, names);
        const asyncPrefix = isAsync ? 'async ' : '';
        const paramPart = paramsText ? `${asyncPrefix}(${paramsText}) => {` : `${asyncPrefix}() => {`;
        const closing = hasOptions ? `}, { ${optionsParts.join(', ')} });` : '});';
        lines.push(`watch(() => ${source}, ${paramPart}\n${body}\n${closing}`);
    });
    if (supportedWatchProps.length > 0) lines.push('');
}

function emitCreatedHooks(lines: string[], state: CompositionScriptState, names: ResolvedIdentifiers): void {
    const { ctx, lifecycleHooks } = state;
    const createdHooks = lifecycleHooks.filter((h) => h.compositionName === null);

    if (createdHooks.length === 0) {
        return;
    }

    // created() has no Composition API hook. Running it directly inside
    // setup preserves its pre-mount timing; async created() stays
    // fire-and-forget so setup itself does not become async.
    for (const hook of createdHooks) {
        const body = rewriteThisInBody(hook.bodyText.trim(), ctx, names);
        if (hook.isAsync) {
            lines.push(`void (async () => {\n${body}\n})();`);
        } else if (hasTopLevelReturn(hook.bodyText)) {
            // A guard clause like `if (…) { return; }` is only legal inside a
            // function, and the setup body is module-level code.
            lines.push(`(() => {\n${body}\n})();`);
        } else {
            lines.push(body);
        }
    }
    lines.push('');
}

function emitRegularHooks(lines: string[], state: CompositionScriptState, names: ResolvedIdentifiers): void {
    const { ctx, regularHooks } = state;

    for (const { compositionName, bodyText, isAsync } of regularHooks) {
        const body = rewriteThisInBody(bodyText, ctx, names);
        const asyncPrefix = isAsync ? 'async ' : '';
        lines.push(`${compositionName}(${asyncPrefix}() => {\n${body}\n});`);
    }
    if (regularHooks.length > 0) lines.push('');
}

function emitSwDefinePublic(lines: string[], state: CompositionScriptState): void {
    const { publicNames } = state;

    // Base mode is auto-private: every top-level binding stays component state,
    // but only the names listed here form the public override API. The marker is
    // mandatory, so an empty object is emitted when nothing is public.
    if (publicNames.length === 0) {
        lines.push('swDefinePublic({});');

        return;
    }

    lines.push('swDefinePublic({');
    publicNames.forEach((n) => lines.push(`    ${n},`));
    lines.push('});');
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
