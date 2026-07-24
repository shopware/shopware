/**
 * @sw-package framework
 */

/**
 * Reads the props macro of a base Shopware setup block.
 *
 * Base lowering leaves the Vue macros in place, so nothing is hoisted or replaced. The
 * analyzer still needs two facts about the props macro: the declared prop names (to reject a setup
 * binding that would collide with a prop) and whether a props macro exists at all (so the footer can
 * forward the props object to `attachOverrides`).
 */

import type { CallExpression } from '@babel/types';
import { type SourceRange, getNodeRange, unwrapTransparentMacroExpression } from './utils';
import { isWithDefaultsCall } from './macros';
import { type MacroCallEntry, getMacroGroupEntry } from './macro-registry';

type MacroName = 'defineProps' | 'withDefaults';

/**
 * Captures the props macro call and its original source range.
 */
type SetupMacroSummary = {
    code: string;
    macroName: MacroName;
    ranges: SourceRange[];
};

type AnalyzeSetupInputsResult = {
    declaredPropNames: string[];
    propsMacro: SetupMacroSummary | null;
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
 * Creates the props macro summary consumed by base lowering.
 */
function createMacroSummary(
    script: string,
    scriptOffset: number,
    call: CallExpression | null,
    macroName: MacroName,
): SetupMacroSummary | null {
    if (!call) {
        return null;
    }

    const range = getNodeRange(call, scriptOffset);
    const code = script.slice(range.start, range.end);

    return {
        code,
        macroName,
        ranges: [
            range,
        ],
    };
}

/**
 * Reads the props macro: its declared prop names and a summary of the call.
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
    // assertMacroRules already enforced modes and multiplicity, so the props macro resolves to at most
    // one entry here.
    const propsEntry = getMacroGroupEntry(entries, 'props');
    const propsMacroName = propsEntry && isWithDefaultsCall(propsEntry.call) ? 'withDefaults' : 'defineProps';

    return {
        declaredPropNames: collectDeclaredPropNames(propsEntry),
        propsMacro: createMacroSummary(script, scriptOffset, propsEntry?.call ?? null, propsMacroName),
    };
}

export { type AnalyzeSetupInputsResult, type SetupMacroSummary, analyzeSetupInputs };
