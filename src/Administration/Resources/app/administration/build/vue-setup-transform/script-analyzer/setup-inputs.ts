/**
 * @sw-package framework
 */

import type { CallExpression } from '@babel/types';
import { ShopwareSetupTransformError } from '../utils/transform-error';
import type { ShopwareSetupMode } from '../utils/shopware-setup-block';
import { type SourceRange, containsRange, getNodeRange } from './utils';
import { isWithDefaultsCall } from './macros';

type SetupInputKind = 'props' | 'emits' | 'expose' | 'slots';

type SetupInputReplacement = SourceRange & {
    kind: SetupInputKind;
};

type MacroName = 'defineProps' | 'withDefaults' | 'defineEmits' | 'defineSlots' | 'defineOptions';

type SetupMacroSummary = {
    code: string;
    macroName: MacroName;
    ranges: SourceRange[];
};

type DefineExposeStatement = {
    call: CallExpression;
};

type DefineOptionsStatement = {
    call: CallExpression;
};

type AnalyzeSetupInputsResult = {
    setupInputReplacements: SetupInputReplacement[];
    propsMacro: SetupMacroSummary | null;
    emitsMacro: SetupMacroSummary | null;
    slotsMacro: SetupMacroSummary | null;
    optionsMacro: SetupMacroSummary | null;
};

/**
 * Returns macro calls after filtering `defineProps()` calls nested inside `withDefaults(...)`.
 */
function getPropsMacroCalls({
    definePropsCalls,
    withDefaultsCalls,
    scriptOffset,
}: {
    definePropsCalls: CallExpression[];
    withDefaultsCalls: CallExpression[];
    scriptOffset: number;
}): CallExpression[] {
    const withDefaultsRanges = withDefaultsCalls.map((call) => getNodeRange(call, scriptOffset));
    const standaloneDefinePropsCalls = definePropsCalls.filter((call) => {
        const definePropsRange = getNodeRange(call, scriptOffset);

        return !withDefaultsRanges.some((withDefaultsRange) => containsRange(withDefaultsRange, definePropsRange));
    });

    return [
        ...withDefaultsCalls,
        ...standaloneDefinePropsCalls,
    ].sort((a, b) => getNodeRange(a, scriptOffset).start - getNodeRange(b, scriptOffset).start);
}

/**
 * Validates base-only setup input macros and returns sorted macro call groups.
 */
function validateBaseSetupMacros({
    mode,
    scriptOffset,
    propsMacroCalls,
    defineEmitsCalls,
    defineExposeStatements,
    defineExposeCalls,
    defineSlotsCalls,
    defineOptionsStatements,
    defineOptionsCalls,
}: {
    mode: ShopwareSetupMode;
    scriptOffset: number;
    propsMacroCalls: CallExpression[];
    defineEmitsCalls: CallExpression[];
    defineExposeStatements: DefineExposeStatement[];
    defineExposeCalls: CallExpression[];
    defineSlotsCalls: CallExpression[];
    defineOptionsStatements: DefineOptionsStatement[];
    defineOptionsCalls: CallExpression[];
}): { emitsMacroCalls: CallExpression[]; slotsMacroCalls: CallExpression[] } {
    if (mode === 'override' && propsMacroCalls.length > 0) {
        const firstPropsMacro = propsMacroCalls[0];
        const macroName = isWithDefaultsCall(firstPropsMacro) ? 'withDefaults' : 'defineProps';

        throw new ShopwareSetupTransformError(
            `${macroName}() is only supported in base Shopware setup blocks.`,
            scriptOffset + getNodeRange(firstPropsMacro, scriptOffset).start,
        );
    }

    if (propsMacroCalls.length > 1) {
        throw new ShopwareSetupTransformError(
            'Only one props declaration macro is allowed in a base Shopware setup block.',
            scriptOffset + getNodeRange(propsMacroCalls[1], scriptOffset).start,
        );
    }

    const emitsMacroCalls = [...defineEmitsCalls].sort(
        (a, b) => getNodeRange(a, scriptOffset).start - getNodeRange(b, scriptOffset).start,
    );

    if (mode === 'override' && emitsMacroCalls.length > 0) {
        throw new ShopwareSetupTransformError(
            'defineEmits() is only supported in base Shopware setup blocks.',
            scriptOffset + getNodeRange(emitsMacroCalls[0], scriptOffset).start,
        );
    }

    if (emitsMacroCalls.length > 1) {
        throw new ShopwareSetupTransformError(
            'Only one defineEmits() call is allowed in a base Shopware setup block.',
            scriptOffset + getNodeRange(emitsMacroCalls[1], scriptOffset).start,
        );
    }

    const topLevelDefineExposeCalls = new Set(defineExposeStatements.map((entry) => entry.call));

    defineExposeCalls.forEach((call) => {
        if (topLevelDefineExposeCalls.has(call)) {
            return;
        }

        throw new ShopwareSetupTransformError(
            'defineExpose() must be called once at the top level of a base Shopware setup block.',
            scriptOffset + getNodeRange(call, scriptOffset).start,
        );
    });

    if (mode === 'override' && defineExposeStatements.length > 0) {
        throw new ShopwareSetupTransformError(
            'defineExpose() is only supported in base Shopware setup blocks.',
            scriptOffset + getNodeRange(defineExposeStatements[0].call, scriptOffset).start,
        );
    }

    if (defineExposeStatements.length > 1) {
        throw new ShopwareSetupTransformError(
            'Only one defineExpose() call is allowed in a base Shopware setup block.',
            scriptOffset + getNodeRange(defineExposeStatements[1].call, scriptOffset).start,
        );
    }

    const slotsMacroCalls = [...defineSlotsCalls].sort(
        (a, b) => getNodeRange(a, scriptOffset).start - getNodeRange(b, scriptOffset).start,
    );

    if (mode === 'override' && slotsMacroCalls.length > 0) {
        throw new ShopwareSetupTransformError(
            'defineSlots() is only supported in base Shopware setup blocks.',
            scriptOffset + getNodeRange(slotsMacroCalls[0], scriptOffset).start,
        );
    }

    if (slotsMacroCalls.length > 1) {
        throw new ShopwareSetupTransformError(
            'Only one defineSlots() call is allowed in a base Shopware setup block.',
            scriptOffset + getNodeRange(slotsMacroCalls[1], scriptOffset).start,
        );
    }

    const topLevelDefineOptionsCalls = new Set(defineOptionsStatements.map((entry) => entry.call));

    defineOptionsCalls.forEach((call) => {
        if (topLevelDefineOptionsCalls.has(call)) {
            return;
        }

        throw new ShopwareSetupTransformError(
            'defineOptions() must be called once at the top level of a base Shopware setup block.',
            scriptOffset + getNodeRange(call, scriptOffset).start,
        );
    });

    if (mode === 'override' && defineOptionsStatements.length > 0) {
        throw new ShopwareSetupTransformError(
            'defineOptions() is only supported in base Shopware setup blocks.',
            scriptOffset + getNodeRange(defineOptionsStatements[0].call, scriptOffset).start,
        );
    }

    if (defineOptionsStatements.length > 1) {
        throw new ShopwareSetupTransformError(
            'Only one defineOptions() call is allowed in a base Shopware setup block.',
            scriptOffset + getNodeRange(defineOptionsStatements[1].call, scriptOffset).start,
        );
    }

    return {
        emitsMacroCalls,
        slotsMacroCalls,
    };
}

/**
 * Creates the macro summary consumed by the lowering step.
 */
function createMacroSummaries(
    script: string,
    scriptOffset: number,
    {
        propsMacroCalls,
        emitsMacroCalls,
        slotsMacroCalls,
        defineOptionsStatements,
    }: {
        propsMacroCalls: CallExpression[];
        emitsMacroCalls: CallExpression[];
        slotsMacroCalls: CallExpression[];
        defineOptionsStatements: DefineOptionsStatement[];
    },
): Omit<AnalyzeSetupInputsResult, 'setupInputReplacements'> {
    const propsMacroCall = propsMacroCalls[0];
    const emitsMacroCall = emitsMacroCalls[0];
    const slotsMacroCall = slotsMacroCalls[0];
    const optionsMacroCall = defineOptionsStatements[0]?.call;
    const propsMacroRange = propsMacroCall ? getNodeRange(propsMacroCall, scriptOffset) : null;
    const emitsMacroRange = emitsMacroCall ? getNodeRange(emitsMacroCall, scriptOffset) : null;
    const slotsMacroRange = slotsMacroCall ? getNodeRange(slotsMacroCall, scriptOffset) : null;
    const optionsMacroRange = optionsMacroCall ? getNodeRange(optionsMacroCall, scriptOffset) : null;

    return {
        propsMacro: propsMacroRange
            ? {
                  code: script.slice(propsMacroRange.start, propsMacroRange.end),
                  macroName: isWithDefaultsCall(propsMacroCall) ? 'withDefaults' : 'defineProps',
                  ranges: [
                      propsMacroRange,
                  ],
              }
            : null,
        emitsMacro: emitsMacroRange
            ? {
                  code: script.slice(emitsMacroRange.start, emitsMacroRange.end),
                  macroName: 'defineEmits',
                  ranges: [
                      emitsMacroRange,
                  ],
              }
            : null,
        slotsMacro: slotsMacroRange
            ? {
                  code: script.slice(slotsMacroRange.start, slotsMacroRange.end),
                  macroName: 'defineSlots',
                  ranges: [
                      slotsMacroRange,
                  ],
              }
            : null,
        optionsMacro: optionsMacroRange
            ? {
                  code: `${script.slice(optionsMacroRange.start, optionsMacroRange.end)};`,
                  macroName: 'defineOptions',
                  ranges: [
                      optionsMacroRange,
                  ],
              }
            : null,
    };
}

/**
 * Collects, validates, and summarizes setup input macros.
 */
function analyzeSetupInputs(
    script: string,
    {
        mode,
        scriptOffset,
        definePropsCalls,
        withDefaultsCalls,
        defineEmitsCalls,
        defineExposeStatements,
        defineExposeCalls,
        defineSlotsCalls,
        defineOptionsStatements,
        defineOptionsCalls,
    }: {
        mode: ShopwareSetupMode;
        scriptOffset: number;
        definePropsCalls: CallExpression[];
        withDefaultsCalls: CallExpression[];
        defineEmitsCalls: CallExpression[];
        defineExposeStatements: DefineExposeStatement[];
        defineExposeCalls: CallExpression[];
        defineSlotsCalls: CallExpression[];
        defineOptionsStatements: DefineOptionsStatement[];
        defineOptionsCalls: CallExpression[];
    },
): AnalyzeSetupInputsResult {
    const propsMacroCalls = getPropsMacroCalls({
        definePropsCalls,
        withDefaultsCalls,
        scriptOffset,
    });
    const { emitsMacroCalls, slotsMacroCalls } = validateBaseSetupMacros({
        mode,
        scriptOffset,
        propsMacroCalls,
        defineEmitsCalls,
        defineExposeStatements,
        defineExposeCalls,
        defineSlotsCalls,
        defineOptionsStatements,
        defineOptionsCalls,
    });
    const setupInputReplacements: SetupInputReplacement[] = [
        ...propsMacroCalls.map((call) => ({
            ...getNodeRange(call, scriptOffset),
            kind: 'props' as const,
        })),
        ...emitsMacroCalls.map((call) => ({
            ...getNodeRange(call, scriptOffset),
            kind: 'emits' as const,
        })),
        ...defineExposeStatements.map((entry) => ({
            ...getNodeRange(entry.call.callee, scriptOffset),
            kind: 'expose' as const,
        })),
        ...slotsMacroCalls.map((call) => ({
            ...getNodeRange(call, scriptOffset),
            kind: 'slots' as const,
        })),
    ];

    return {
        setupInputReplacements,
        ...createMacroSummaries(script, scriptOffset, {
            propsMacroCalls,
            emitsMacroCalls,
            slotsMacroCalls,
            defineOptionsStatements,
        }),
    };
}

export {
    type AnalyzeSetupInputsResult,
    type DefineExposeStatement,
    type DefineOptionsStatement,
    type SetupInputKind,
    type SetupInputReplacement,
    type SetupMacroSummary,
    analyzeSetupInputs,
};
