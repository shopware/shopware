/**
 * @sw-package framework
 */

/**
 * Normalizes Vue setup input macros for Shopware setup lowering.
 *
 * Base components may declare props, emits, slots, expose, and options through Vue macros. This
 * module validates those declarations and records the exact ranges that must be hoisted or replaced
 * when the setup body is moved into Shopware's extendable callback.
 */

import type { CallExpression } from '@babel/types';
import { type SourceRange, getNodeRange, unwrapTransparentMacroExpression } from './utils';
import { isWithDefaultsCall } from './macros';
import { type MacroCallEntry, getMacroEntry, getMacroGroupEntry } from './macro-registry';

type SetupInputKind = 'props' | 'emits' | 'slots';

type SetupInputReplacement = SourceRange & {
    kind: SetupInputKind;
    // The macro is its own top-level statement (`defineExpose({...});`), not an initializer
    // (`const props = defineProps(...)`). Its replacement `(__swSetup...)` opens a statement, so the
    // lowerer prefixes `;` to stop automatic-semicolon insertion gluing it onto the previous line.
    statementInitial: boolean;
};

type MacroName = 'defineProps' | 'withDefaults' | 'defineEmits' | 'defineSlots' | 'defineOptions' | 'defineExpose';

/**
 * Captures one hoisted Vue setup macro call and the original source ranges that produced it.
 *
 * Lowering reuses the saved source text at module scope while replacing the original in-body call
 * with generated callback inputs.
 */
type SetupMacroSummary = {
    code: string;
    macroName: MacroName;
    ranges: SourceRange[];
};

type AnalyzeSetupInputsResult = {
    setupInputReplacements: SetupInputReplacement[];
    declaredPropNames: string[];
    propsMacro: SetupMacroSummary | null;
    emitsMacro: SetupMacroSummary | null;
    slotsMacro: SetupMacroSummary | null;
    optionsMacro: SetupMacroSummary | null;
    exposeMacro: SetupMacroSummary | null;
};

/**
 * Collects the statically declared prop names from the props macro.
 *
 * Covers the forms the transform can read without a type resolver: the runtime object/array argument
 * (`defineProps({ title: String })`, `defineProps(['title'])`) and an inline type literal
 * (`defineProps<{ title: string }>()`), including through a `withDefaults(...)` wrapper. A prop type
 * that is only a named reference (`defineProps<Props>()`) cannot be resolved here and yields no names.
 */
function collectDeclaredPropNames(propsEntry: MacroCallEntry | null): string[] {
    if (!propsEntry) {
        return [];
    }

    const defineCall = isWithDefaultsCall(propsEntry.call)
        ? unwrapTransparentMacroExpression(propsEntry.call.arguments[0])
        : propsEntry.call;

    if (defineCall?.type !== 'CallExpression') {
        return [];
    }

    const names: string[] = [];

    const runtimeArgument = defineCall.arguments[0];

    if (runtimeArgument?.type === 'ObjectExpression') {
        runtimeArgument.properties.forEach((property) => {
            if (property.type !== 'ObjectProperty' || property.computed) {
                return;
            }

            if (property.key.type === 'Identifier') {
                names.push(property.key.name);
            } else if (property.key.type === 'StringLiteral') {
                names.push(property.key.value);
            }
        });
    } else if (runtimeArgument?.type === 'ArrayExpression') {
        runtimeArgument.elements.forEach((element) => {
            if (element?.type === 'StringLiteral') {
                names.push(element.value);
            }
        });
    }

    const typeArgument = defineCall.typeParameters?.params[0];

    if (typeArgument?.type === 'TSTypeLiteral') {
        typeArgument.members.forEach((member) => {
            if (member.type !== 'TSPropertySignature' || member.computed || !member.key) {
                return;
            }

            if (member.key.type === 'Identifier') {
                names.push(member.key.name);
            } else if (member.key.type === 'StringLiteral') {
                names.push(member.key.value);
            }
        });
    }

    return names;
}

/**
 * Creates the macro summary consumed by the lowering step.
 */
function createMacroSummary(
    script: string,
    scriptOffset: number,
    call: CallExpression | null,
    macroName: MacroName,
    options: { appendSemicolon?: boolean } = {},
): SetupMacroSummary | null {
    if (!call) {
        return null;
    }

    const range = getNodeRange(call, scriptOffset);
    const code = script.slice(range.start, range.end);

    return {
        code: options.appendSemicolon ? `${code};` : code,
        macroName,
        ranges: [
            range,
        ],
    };
}

/**
 * Collects, validates, and summarizes setup input macros.
 */
function analyzeSetupInputs(
    script: string,
    {
        scriptOffset,
        entries,
    }: {
        scriptOffset: number;
        entries: MacroCallEntry[];
    },
): AnalyzeSetupInputsResult {
    // assertMacroRules already enforced modes and multiplicity, so each macro resolves to at most one
    // entry here. Statement-form expose/options are the only hoist-relevant forms; a declaration such
    // as `const o = defineOptions(...)` stays ordinary callback code.
    const propsEntry = getMacroGroupEntry(entries, 'props');
    const emitsEntry = getMacroEntry(entries, 'defineEmits');
    const slotsEntry = getMacroEntry(entries, 'defineSlots');
    const exposeEntry = getMacroEntry(entries, 'defineExpose', 'statement');
    const optionsEntry = getMacroEntry(entries, 'defineOptions', 'statement');

    const setupInputReplacements: SetupInputReplacement[] = [];

    if (propsEntry) {
        setupInputReplacements.push({
            ...getNodeRange(propsEntry.call, scriptOffset),
            kind: 'props',
            statementInitial: propsEntry.form === 'statement',
        });
    }

    if (emitsEntry) {
        setupInputReplacements.push({
            ...getNodeRange(emitsEntry.call, scriptOffset),
            kind: 'emits',
            statementInitial: emitsEntry.form === 'statement',
        });
    }

    if (slotsEntry) {
        setupInputReplacements.push({
            ...getNodeRange(slotsEntry.call, scriptOffset),
            kind: 'slots',
            statementInitial: slotsEntry.form === 'statement',
        });
    }

    const propsMacroName = propsEntry && isWithDefaultsCall(propsEntry.call) ? 'withDefaults' : 'defineProps';

    return {
        setupInputReplacements,
        declaredPropNames: collectDeclaredPropNames(propsEntry),
        propsMacro: createMacroSummary(script, scriptOffset, propsEntry?.call ?? null, propsMacroName),
        emitsMacro: createMacroSummary(script, scriptOffset, emitsEntry?.call ?? null, 'defineEmits'),
        slotsMacro: createMacroSummary(script, scriptOffset, slotsEntry?.call ?? null, 'defineSlots'),
        optionsMacro: createMacroSummary(script, scriptOffset, optionsEntry?.call ?? null, 'defineOptions', {
            appendSemicolon: true,
        }),
        // defineExpose is re-emitted as a real macro at the generated script-setup footer (after the
        // extendable-setup destructure), where the exposed bindings are in scope. Keeping the real macro
        // - instead of a runtime `context.expose()` call - means Vue wires expose exactly once and does
        // not warn about a second expose.
        exposeMacro: createMacroSummary(script, scriptOffset, exposeEntry?.call ?? null, 'defineExpose', {
            appendSemicolon: true,
        }),
    };
}

export {
    type AnalyzeSetupInputsResult,
    type SetupInputKind,
    type SetupInputReplacement,
    type SetupMacroSummary,
    analyzeSetupInputs,
};
