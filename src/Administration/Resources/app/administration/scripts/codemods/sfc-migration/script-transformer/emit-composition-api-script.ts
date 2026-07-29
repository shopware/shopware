import { quoteJsString } from '../string-literals';
import type { CompositionScriptState } from './composition-script-state';
import { indentBlock, sanitizeTodoCommentText } from './helpers';
import { attrsIdent, emitIdent, routeIdent, routerIdent, slotsIdent, tIdent } from './identifiers';
import { IDENTIFIER_TEMPLATE_MARKER, identTemplate, renderIdentifierTemplates } from './identifier-template';
import type { IdentifierTemplate, IdentifierToken, ScriptLine } from './identifier-template';
import { buildWatchSource, rewriteThisInBody } from './rewrite-this';

export function emitCompositionApiScript(state: CompositionScriptState): string {
    const lines: ScriptLine[] = [];

    emitTodoComments(lines, state);
    emitModuleLevelCode(lines, state);
    emitCompilerMacros(lines, state);
    emitImports(lines, state);
    emitComposableDeclarations(lines, state);
    emitTemplateRefs(lines, state);
    emitCreateExtendableSetup(lines, state);

    return renderIdentifierTemplates(lines, collectTakenNames(state)).join('\n');
}

function emitTodoComments(lines: ScriptLine[], state: CompositionScriptState): void {
    const { todoComments } = state;

    if (todoComments.length > 0) {
        lines.push(todoComments.join('\n'));
        lines.push('');
    }
}

function emitModuleLevelCode(lines: ScriptLine[], state: CompositionScriptState): void {
    const { moduleLevelCode } = state;

    if (moduleLevelCode) {
        lines.push(moduleLevelCode);
        lines.push('');
    }
}

function emitCompilerMacros(lines: ScriptLine[], state: CompositionScriptState): void {
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
        // TODO: Silent ignore: props definitions that reference module-local
        // declarations are emitted into defineProps even though script setup
        // compiler macros are hoisted and cannot depend on setup locals.
        lines.push(`const props = defineProps(${propsText});`);
    } else {
        lines.push(`const props = defineProps({});`);
    }

    if (emitsDefinition.objectText !== null) {
        // TODO: Silent ignore: emits validators that reference module-local
        // declarations are emitted into defineEmits even though script setup
        // compiler macros are hoisted and cannot depend on setup locals.
        lines.push(identTemplate`const ${emitIdent} = defineEmits(${emitsDefinition.objectText});`);
    } else if (effectiveEmitsKeys.length > 0) {
        const emitsList = effectiveEmitsKeys.map((k) => `'${k}'`).join(', ');
        lines.push(identTemplate`const ${emitIdent} = defineEmits([${emitsList}]);`);
    } else if (usedComposables.needsEmit) {
        lines.push(identTemplate`const ${emitIdent} = defineEmits([]);`);
    }
    lines.push('');
}

function emitImports(lines: ScriptLine[], state: CompositionScriptState): void {
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

function emitComposableDeclarations(lines: ScriptLine[], state: CompositionScriptState): void {
    const { usedComposables } = state;

    if (usedComposables.needsRouter) lines.push(identTemplate`const ${routerIdent} = useRouter();`);
    if (usedComposables.needsRoute) lines.push(identTemplate`const ${routeIdent} = useRoute();`);
    if (usedComposables.needsSlots) lines.push(identTemplate`const ${slotsIdent} = useSlots();`);
    if (usedComposables.needsAttrs) lines.push(identTemplate`const ${attrsIdent} = useAttrs();`);
    if (usedComposables.needsI18n) lines.push(createI18nDeclarationTemplate());
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

function emitTemplateRefs(lines: ScriptLine[], state: CompositionScriptState): void {
    const { templateRefNames } = state;

    for (const refName of templateRefNames) {
        lines.push(`const ${refName} = ref(null);`);
    }
    if (templateRefNames.length > 0) lines.push('');
}

function emitCreateExtendableSetup(lines: ScriptLine[], state: CompositionScriptState): void {
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

    // createExtendableSetup is the Shopware compatibility layer for
    // overrideComponentSetup. Only names returned under `public` are available
    // to templates and downstream overrides.
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
        lines.push(identTemplate`        const ${name} = ref(${rewrittenValue});`);
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
            // Property-assignment methods often wrap callbacks in helpers such
            // as debounce(). Preserve the wrapper expression instead of
            // flattening it into a plain arrow method.
            const normalizedRawText = rawText.replace(/\bfunction\s+\w*\s*\(([^)]*)\)\s*\{/g, '($1) => {');
            const rewritten = rewriteThisInBody(normalizedRawText, ctx, 'expression');
            lines.push(identTemplate`        const ${name} = ${rewritten};`);
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
                identTemplate`        watch(() => ${source}, (...args) => ${handlerName}(...args)${hasOptions ? `, { ${optionsParts.join(', ')} }` : ''});`,
            );
            return;
        }

        const body = rewriteThisInBody(bodyText ?? '', ctx);
        const asyncPrefix = isAsync ? 'async ' : '';
        const paramPart = paramsText ? `${asyncPrefix}(${paramsText}) => {` : `${asyncPrefix}() => {`;
        lines.push(identTemplate`        watch(() => ${source}, ${paramPart}`);
        lines.push(indentBlock(body, 12));
        lines.push(hasOptions ? `        }, { ${optionsParts.join(', ')} });` : `        });`);
    });
    if (supportedWatchProps.length > 0) lines.push('');

    const createdHooks = lifecycleHooks.filter((h) => h.compositionName === null);
    if (createdHooks.length > 0) {
        // created() has no Composition API hook. Running it directly inside
        // setup preserves its pre-mount timing; async created() stays
        // fire-and-forget so setup itself does not become async.
        for (const hook of createdHooks) {
            const body = rewriteThisInBody(hook.bodyText.trim(), ctx);
            if (hook.isAsync) {
                lines.push('        void (async () => {');
                lines.push(indentBlock(body, 12));
                lines.push('        })();');
            } else {
                lines.push(indentBlock(body, 8));
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

function collectTakenNames(state: CompositionScriptState): Set<string> {
    return new Set([
        ...state.existingBindingNames,
        ...state.publicNames,
        ...state.templateRefNames,
        'props',
    ]);
}

function createI18nDeclarationTemplate(): IdentifierTemplate {
    return {
        [IDENTIFIER_TEMPLATE_MARKER]: true,
        getIdentifierTokens(): IdentifierToken[] {
            return [tIdent];
        },
        render(resolve: (token: IdentifierToken) => string): string {
            const resolvedName = resolve(tIdent);

            return resolvedName === 't' ? 'const { t } = useI18n();' : `const { t: ${resolvedName} } = useI18n();`;
        },
    };
}
