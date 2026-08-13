import { quoteJsString } from '../string-literals';
import { hasTopLevelReturn } from './ast';
import type { CompositionScriptState } from './composition-script-state';
import { sanitizeTodoCommentText } from './helpers';
import type { ResolvedIdentifiers } from './resolve-identifiers';
import { buildWatchSource, rewriteThisInBody } from './rewrite-this';

/**
 * Emits the setup body as native `<script setup>` code. The build-time transform
 * in `build/vue-setup-transform` lowers it into the extension runtime, so no
 * `createExtendableSetup()` wrapper is written here.
 *
 * Emitters only produce valid code, never layout: `generate-sfc.ts` runs the
 * assembled SFC through prettier, so indentation inside a line does not matter.
 * Blank lines do — prettier keeps them, and they are what groups the output.
 *
 * See `technical-docs/03-extensibility/07-native-setup-authoring.md`.
 */
export function emitNativeSetup(lines: string[], state: CompositionScriptState, names: ResolvedIdentifiers): void {
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
