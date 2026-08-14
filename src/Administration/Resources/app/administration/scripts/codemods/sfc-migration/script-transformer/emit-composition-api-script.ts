import type { CompositionScriptState } from './composition-script-state';
import { emitCreateExtendableSetup } from './emit-create-extendable-setup';
import { emitIdent } from './identifiers';
import type { ComposableDescriptor } from './composable-registry';
import type { ActiveComposable, ComposableArgument } from './types';
import {
    IDENTIFIER_TEMPLATE_MARKER,
    identTemplate,
    isIdentifierTemplate,
    renderIdentifierTemplates,
} from './identifier-template';
import type { IdentifierTemplate, IdentifierToken, ScriptLine, ScriptSnippet } from './identifier-template';

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
        lines.push(`const props = defineProps(${propsText});`);
    } else {
        lines.push(`const props = defineProps({});`);
    }

    if (emitsDefinition.objectText !== null) {
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
    const { activeComposables, vueImports } = state;

    lines.push(`import { createExtendableSetup } from 'src/app/adapter/composition-extension-system';`);
    if (vueImports.length > 0) {
        lines.push(`import { ${[...new Set(vueImports)].join(', ')} } from 'vue';`);
    }

    // Group non-'vue' composable imports by source, in first-seen order.
    // 'vue'-sourced composables (useSlots/useAttrs) are already part of the vue
    // import line above, so they are skipped here.
    const importsBySource = new Map<string, string[]>();
    for (const { descriptor } of activeComposables) {
        if (descriptor.import.source === 'vue') {
            continue;
        }
        const names = importsBySource.get(descriptor.import.source) ?? [];
        if (!names.includes(descriptor.import.name)) {
            names.push(descriptor.import.name);
        }
        importsBySource.set(descriptor.import.source, names);
    }
    for (const [source, names] of importsBySource) {
        lines.push(`import { ${names.join(', ')} } from '${source}';`);
    }

    lines.push('');
}

function emitComposableDeclarations(lines: ScriptLine[], state: CompositionScriptState): void {
    const { activeComposables } = state;

    for (const active of activeComposables) {
        lines.push(buildComposableDeclaration(active));
    }
    if (activeComposables.length > 0) {
        lines.push('');
    }
}

function buildComposableDeclaration(active: ActiveComposable): ScriptLine {
    const { descriptor, memberKeys, argumentEntries } = active;

    if (descriptor.declarationStyle === 'whole') {
        // `binding` is always set for whole descriptors.
        return identTemplate`const ${descriptor.binding as IdentifierToken} = ${descriptor.import.name}(${buildArgumentsSnippet(argumentEntries)});`;
    }

    return createDestructureDeclarationTemplate(descriptor, memberKeys, argumentEntries);
}

/**
 * Renders the composable's single options argument, e.g.
 * `{ item: () => props.item }`, or an empty string when the descriptor declares
 * no instance dependencies.
 */
function buildArgumentsSnippet(argumentEntries: ComposableArgument[]): ScriptSnippet {
    if (argumentEntries.length === 0) {
        return '';
    }

    return {
        [IDENTIFIER_TEMPLATE_MARKER]: true,
        getIdentifierTokens(): IdentifierToken[] {
            return argumentEntries.flatMap(({ valueSnippet }) =>
                isIdentifierTemplate(valueSnippet) ? valueSnippet.getIdentifierTokens() : [],
            );
        },
        render(resolve: (token: IdentifierToken) => string): string {
            const entries = argumentEntries.map(
                ({ option, valueSnippet }) =>
                    `    ${option}: ${isIdentifierTemplate(valueSnippet) ? valueSnippet.render(resolve) : valueSnippet},`,
            );

            return `{\n${entries.join('\n')}\n}`;
        },
    };
}

function emitTemplateRefs(lines: ScriptLine[], state: CompositionScriptState): void {
    const { templateRefNames } = state;

    for (const refName of templateRefNames) {
        lines.push(`const ${refName} = ref(null);`);
    }
    if (templateRefNames.length > 0) lines.push('');
}

function collectTakenNames(state: CompositionScriptState): Set<string> {
    return new Set([
        ...state.existingBindingNames,
        ...state.publicNames,
        ...state.templateRefNames,
        'props',
    ]);
}

/**
 * Emits `const { a, b: b2 } = useX();` for a destructure descriptor. Each unique
 * binding renders as its source key, or `sourceKey: renamed` when the identifier
 * resolver had to rename it to avoid a collision.
 */
function createDestructureDeclarationTemplate(
    descriptor: ComposableDescriptor,
    memberKeys: string[],
    argumentEntries: ComposableArgument[],
): IdentifierTemplate {
    const usedKeys = new Set(memberKeys);
    const seen = new Set<IdentifierToken>();
    const entries: { ident: IdentifierToken; sourceKey: string }[] = [];
    for (const [key, member] of Object.entries(descriptor.members)) {
        if (!usedKeys.has(key) || seen.has(member.ident)) {
            continue;
        }
        seen.add(member.ident);
        entries.push({ ident: member.ident, sourceKey: member.sourceKey ?? '' });
    }

    const argumentsSnippet = buildArgumentsSnippet(argumentEntries);

    return {
        [IDENTIFIER_TEMPLATE_MARKER]: true,
        getIdentifierTokens(): IdentifierToken[] {
            return [
                ...entries.map((entry) => entry.ident),
                ...(isIdentifierTemplate(argumentsSnippet) ? argumentsSnippet.getIdentifierTokens() : []),
            ];
        },
        render(resolve: (token: IdentifierToken) => string): string {
            const parts = entries.map(({ ident, sourceKey }) => {
                const resolved = resolve(ident);

                return resolved === sourceKey ? sourceKey : `${sourceKey}: ${resolved}`;
            });
            const args = isIdentifierTemplate(argumentsSnippet) ? argumentsSnippet.render(resolve) : argumentsSnippet;

            return `const { ${parts.join(', ')} } = ${descriptor.import.name}(${args});`;
        },
    };
}
