/**
 * @sw-package framework
 */

const { ShopwareSetupTransformError } = require('../utils/transform-error');
const {
    containsRange,
    getNodeRange,
} = require('./utils');
const { isWithDefaultsCall } = require('./macros');

/**
 * Returns macro calls after filtering `defineProps()` calls nested inside `withDefaults(...)`.
 *
 * @param {object} params
 * @param {import('@babel/types').CallExpression[]} params.definePropsCalls
 * @param {import('@babel/types').CallExpression[]} params.withDefaultsCalls
 * @param {number} params.scriptOffset
 * @returns {import('@babel/types').CallExpression[]}
 */
function getPropsMacroCalls({ definePropsCalls, withDefaultsCalls, scriptOffset }) {
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
 *
 * @param {object} params
 * @param {'base' | 'override'} params.mode
 * @param {number} params.scriptOffset
 * @param {import('@babel/types').CallExpression[]} params.propsMacroCalls
 * @param {import('@babel/types').CallExpression[]} params.defineEmitsCalls
 * @param {{ call: import('@babel/types').CallExpression }[]} params.defineExposeStatements
 * @param {import('@babel/types').CallExpression[]} params.defineExposeCalls
 * @param {import('@babel/types').CallExpression[]} params.defineSlotsCalls
 * @param {{ call: import('@babel/types').CallExpression }[]} params.defineOptionsStatements
 * @param {import('@babel/types').CallExpression[]} params.defineOptionsCalls
 * @returns {{ emitsMacroCalls: import('@babel/types').CallExpression[], slotsMacroCalls: import('@babel/types').CallExpression[] }}
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
}) {
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
 *
 * @param {string} script
 * @param {number} scriptOffset
 * @param {object} params
 * @param {import('@babel/types').CallExpression[]} params.propsMacroCalls
 * @param {import('@babel/types').CallExpression[]} params.emitsMacroCalls
 * @param {import('@babel/types').CallExpression[]} params.slotsMacroCalls
 * @param {{ call: import('@babel/types').CallExpression }[]} params.defineOptionsStatements
 * @returns {object}
 */
function createMacroSummaries(script, scriptOffset, {
    propsMacroCalls,
    emitsMacroCalls,
    slotsMacroCalls,
    defineOptionsStatements,
}) {
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
 *
 * @param {string} script
 * @param {object} params
 * @param {'base' | 'override'} params.mode
 * @param {number} params.scriptOffset
 * @param {import('@babel/types').CallExpression[]} params.definePropsCalls
 * @param {import('@babel/types').CallExpression[]} params.withDefaultsCalls
 * @param {import('@babel/types').CallExpression[]} params.defineEmitsCalls
 * @param {{ call: import('@babel/types').CallExpression }[]} params.defineExposeStatements
 * @param {import('@babel/types').CallExpression[]} params.defineExposeCalls
 * @param {import('@babel/types').CallExpression[]} params.defineSlotsCalls
 * @param {{ call: import('@babel/types').CallExpression }[]} params.defineOptionsStatements
 * @param {import('@babel/types').CallExpression[]} params.defineOptionsCalls
 * @param {import('@babel/types').CallExpression[]} params.useSwPropsCalls
 * @returns {object}
 */
function analyzeSetupInputs(script, {
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
    useSwPropsCalls,
}) {
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
    const setupInputReplacements = [
        ...propsMacroCalls.map((call) => ({
            ...getNodeRange(call, scriptOffset),
            kind: 'props',
        })),
        ...useSwPropsCalls.map((call) => ({
            ...getNodeRange(call, scriptOffset),
            kind: 'props',
        })),
        ...emitsMacroCalls.map((call) => ({
            ...getNodeRange(call, scriptOffset),
            kind: 'emits',
        })),
        ...defineExposeStatements.map((entry) => ({
            ...getNodeRange(entry.call.callee, scriptOffset),
            kind: 'expose',
        })),
        ...slotsMacroCalls.map((call) => ({
            ...getNodeRange(call, scriptOffset),
            kind: 'slots',
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

module.exports = {
    analyzeSetupInputs,
};
