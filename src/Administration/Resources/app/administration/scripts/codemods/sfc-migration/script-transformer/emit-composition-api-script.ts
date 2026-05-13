import { quoteJsString } from '../string-literals';
import type { CompositionScriptState } from './composition-script-state';
import { indentBlock, sanitizeTodoCommentText } from './helpers';
import { buildWatchSource, rewriteThisInBody } from './rewrite-this';

export function emitCompositionApiScript(state: CompositionScriptState): string {
    const lines: string[] = [];

    emitTodoComments(lines, state);
    emitModuleLevelCode(lines, state);
    emitCompilerMacros(lines, state);
    emitImports(lines, state);
    emitComposableDeclarations(lines, state);
    emitTemplateRefs(lines, state);
    emitCreateExtendableSetup(lines, state);

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

function emitCompilerMacros(lines: string[], state: CompositionScriptState): void {
    const { componentNameValue, effectiveEmitsKeys, emitsDefinition, inheritAttrs, propsText, usedComposables } = state;
    const defineOptionsArgs = [
        !inheritAttrs ? 'inheritAttrs: false' : '',
        componentNameValue ? `name: ${componentNameValue}` : '',
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
        lines.push(`const emit = defineEmits([]);`);
    }
    lines.push('');
}

function emitImports(lines: string[], state: CompositionScriptState): void {
    const { usedComposables, vueImports } = state;

    lines.push(`import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';`);
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
    lines.push('');
}

function emitComposableDeclarations(lines: string[], state: CompositionScriptState): void {
    const { usedComposables } = state;

    if (usedComposables.needsRouter) lines.push(`const router = useRouter();`);
    if (usedComposables.needsRoute) lines.push(`const route = useRoute();`);
    if (usedComposables.needsSlots) lines.push(`const slots = useSlots();`);
    if (usedComposables.needsAttrs) lines.push(`const attrs = useAttrs();`);
    if (usedComposables.needsI18n) lines.push(`const { t } = useI18n();`);
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

function emitCreateExtendableSetup(lines: string[], state: CompositionScriptState): void {
    const {
        ctx,
        injectNames,
        lifecycleHooks,
        propNames,
        publicNames,
        registration,
        regularHooks,
        supportedComputedProps,
        supportedDataProps,
        supportedInjectProps,
        supportedMethodProps,
        supportedWatchProps,
        unsupportedWatchEntries,
    } = state;

    if (publicNames.length > 0) {
        lines.push('const {');
        publicNames.forEach((n) => lines.push(`    ${n},`));
        lines.push('} = createExtendableSetup(');
    } else {
        lines.push('createExtendableSetup(');
    }

    lines.push('    {');
    lines.push(`        name: '${registration.componentName}',`);
    lines.push('        props,');
    lines.push('    },');
    lines.push('    () => {');

    supportedInjectProps.forEach(({ localName, sourceKey, defaultValueText, treatDefaultAsFactory }) => {
        const args = [quoteJsString(sourceKey)];

        if (defaultValueText !== undefined) {
            args.push(defaultValueText);

            if (treatDefaultAsFactory) {
                args.push('true');
            }
        }

        lines.push(`        const ${localName} = inject(${args.join(', ')});`);
    });
    if (supportedInjectProps.length > 0) lines.push('');

    supportedDataProps.forEach(({ name, valueText }) => {
        const rewrittenValue = rewriteThisInBody(valueText, ctx, 'expression');
        lines.push(`        const ${name} = ref(${rewrittenValue});`);
    });
    if (supportedDataProps.length > 0) lines.push('');

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

    supportedMethodProps.forEach(({ name, paramsText, bodyText, isAsync, rawText }) => {
        if (rawText !== undefined) {
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

    unsupportedWatchEntries.forEach((entry) => {
        lines.push(`        // TODO: migrate watch entry manually: ${sanitizeTodoCommentText(entry)}`);
    });
    if (unsupportedWatchEntries.length > 0) lines.push('');

    supportedWatchProps.forEach(({ name, paramsText, bodyText, handlerName, isAsync, deep, immediate }) => {
        const source = buildWatchSource(name, propNames, injectNames);
        const hasOptions = deep || immediate;
        const optionsParts = [
            deep ? 'deep: true' : '',
            immediate ? 'immediate: true' : '',
        ].filter(Boolean);

        if (handlerName) {
            lines.push(
                `        watch(() => ${source}, (...args) => ${handlerName}(...args)${hasOptions ? `, { ${optionsParts.join(', ')} }` : ''});`,
            );
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

    const createdHooks = lifecycleHooks.filter((h) => h.compositionName === null);
    if (createdHooks.length > 0) {
        for (const hook of createdHooks) {
            const body = rewriteThisInBody(hook.bodyText, ctx);
            if (hook.isAsync) {
                lines.push('        void (async () => {');
                lines.push(indentBlock(body.trim(), 12));
                lines.push('        })();');
            } else {
                lines.push(indentBlock(body.trim(), 8));
            }
        }
        lines.push('');
    }

    for (const { compositionName, bodyText, isAsync } of regularHooks) {
        const body = rewriteThisInBody(bodyText, ctx);
        const asyncPrefix = isAsync ? 'async ' : '';
        lines.push(`        ${compositionName}(${asyncPrefix}() => {`);
        lines.push(indentBlock(body, 12));
        lines.push(`        });`);
    }
    if (regularHooks.length > 0) lines.push('');

    lines.push('        return {');
    lines.push('            public: {');
    publicNames.forEach((n) => lines.push(`                ${n},`));
    lines.push('            },');
    lines.push('        };');
    lines.push('    },');
    lines.push(');');
}
