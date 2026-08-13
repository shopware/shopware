import type { CompositionScriptState } from './composition-script-state';
import { emitNativeSetup } from './emit-native-setup';
import { attrsIdent, emitIdent, routeIdent, routerIdent, slotsIdent, tIdent } from './identifiers';
import { IDENTIFIER_TEMPLATE_MARKER, identTemplate, renderIdentifierTemplates } from './identifier-template';
import type { IdentifierTemplate, IdentifierToken, ScriptLine } from './identifier-template';

export function emitCompositionApiScript(state: CompositionScriptState): string {
    const lines: ScriptLine[] = [];

    emitTodoComments(lines, state);
    emitModuleLevelCode(lines, state);
    emitCompilerMacros(lines, state);
    emitImports(lines, state);
    emitComposableDeclarations(lines, state);
    emitTemplateRefs(lines, state);
    emitNativeSetup(lines, state);

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

    const declarationLines: ScriptLine[] = [];

    // An empty defineProps({}) would only add an unused `props` binding to the
    // component state, so the macro is emitted for real props definitions only.
    if (propsText) {
        declarationLines.push(`const props = defineProps(${propsText});`);
    }

    if (emitsDefinition.objectText !== null) {
        declarationLines.push(identTemplate`const ${emitIdent} = defineEmits(${emitsDefinition.objectText});`);
    } else if (effectiveEmitsKeys.length > 0) {
        const emitsList = effectiveEmitsKeys.map((k) => `'${k}'`).join(', ');
        declarationLines.push(identTemplate`const ${emitIdent} = defineEmits([${emitsList}]);`);
    } else if (usedComposables.needsEmit) {
        declarationLines.push(identTemplate`const ${emitIdent} = defineEmits([]);`);
    }

    if (declarationLines.length === 0) {
        return;
    }

    lines.push(...declarationLines);
    lines.push('');
}

function emitImports(lines: ScriptLine[], state: CompositionScriptState): void {
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
