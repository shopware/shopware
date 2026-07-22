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
import { type SourceRange, getNodeRange } from './utils';
import { isWithDefaultsCall } from './macros';
import { type MacroCallEntry, getMacroEntry, getMacroGroupEntry } from './macro-registry';

type SetupInputKind = 'props' | 'emits' | 'expose' | 'slots';

type SetupInputReplacement = SourceRange & {
    kind: SetupInputKind;
};

type MacroName = 'defineProps' | 'withDefaults' | 'defineEmits' | 'defineSlots' | 'defineOptions';

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
    propsMacro: SetupMacroSummary | null;
    emitsMacro: SetupMacroSummary | null;
    slotsMacro: SetupMacroSummary | null;
    optionsMacro: SetupMacroSummary | null;
};

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
        });
    }

    if (emitsEntry) {
        setupInputReplacements.push({
            ...getNodeRange(emitsEntry.call, scriptOffset),
            kind: 'emits',
        });
    }

    if (exposeEntry) {
        // Only the callee is replaced so the author's argument list survives:
        // `defineExpose({ focus })` -> `(__swSetupContext.expose)({ focus })`.
        setupInputReplacements.push({
            ...getNodeRange(exposeEntry.call.callee, scriptOffset),
            kind: 'expose',
        });
    }

    if (slotsEntry) {
        setupInputReplacements.push({
            ...getNodeRange(slotsEntry.call, scriptOffset),
            kind: 'slots',
        });
    }

    const propsMacroName = propsEntry && isWithDefaultsCall(propsEntry.call) ? 'withDefaults' : 'defineProps';

    return {
        setupInputReplacements,
        propsMacro: createMacroSummary(script, scriptOffset, propsEntry?.call ?? null, propsMacroName),
        emitsMacro: createMacroSummary(script, scriptOffset, emitsEntry?.call ?? null, 'defineEmits'),
        slotsMacro: createMacroSummary(script, scriptOffset, slotsEntry?.call ?? null, 'defineSlots'),
        optionsMacro: createMacroSummary(script, scriptOffset, optionsEntry?.call ?? null, 'defineOptions', {
            appendSemicolon: true,
        }),
    };
}

export { type AnalyzeSetupInputsResult, type SetupInputKind, type SetupInputReplacement, type SetupMacroSummary, analyzeSetupInputs };
