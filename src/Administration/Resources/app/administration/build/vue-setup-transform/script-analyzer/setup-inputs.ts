/**
 * @sw-package framework
 */

/**
 * Reads the props macro of a base Shopware setup block.
 *
 * Base lowering leaves the Vue macros in place, so nothing is hoisted or replaced, and props are read
 * from the component instance at runtime rather than forwarded by the generated footer. The analyzer
 * only needs the declared prop names, to reject a setup binding that would collide with a prop.
 */

import { unwrapTransparentMacroExpression } from './utils';
import { isWithDefaultsCall } from './macros';
import { type MacroCallEntry, getMacroGroupEntry } from './macro-registry';

type AnalyzeSetupInputsResult = {
    declaredPropNames: string[];
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
 * Reads the declared prop names of the base props macro.
 */
function analyzeSetupInputs(entries: MacroCallEntry[]): AnalyzeSetupInputsResult {
    // assertMacroRules already enforced modes, so the props macro resolves to at most one entry here.
    return {
        declaredPropNames: collectDeclaredPropNames(getMacroGroupEntry(entries, 'props')),
    };
}

/**
 * @private
 */
export { type AnalyzeSetupInputsResult, analyzeSetupInputs };
