import type { CompositionScriptState } from './composition-script-state';
import { emitNativeSetup } from './emit-native-setup';
import { resolveIdentifierNames } from './resolve-identifiers';
import type { ResolvedIdentifiers } from './resolve-identifiers';

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
