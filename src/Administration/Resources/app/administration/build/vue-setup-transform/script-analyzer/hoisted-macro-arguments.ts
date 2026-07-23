/**
 * @sw-package framework
 */

/**
 * Guards one edge case: a hoisted macro argument that reads a local setup binding.
 *
 * The transform hoists `defineProps(...)`, `withDefaults(...)`, `defineEmits(...)`, and
 * `defineOptions(...)` to the generated script root, while every other top-level statement moves into
 * the Shopware setup callback. A macro argument that references a callback-local value would therefore
 * read a binding that no longer exists at the root:
 *
 * ```ts
 * const events = ['save'];
 * const emit = defineEmits(events); // hoisted -> `events` is out of reach at the root
 * ```
 *
 * The reference/shadowing analysis lives in `flow-analysis`; this module is just the guard that runs
 * it over each hoisted macro's value and type arguments.
 */

import type { CallExpression, Node as BabelNode } from '@babel/types';
import { ShopwareSetupTransformError } from '../utils/transform-error';
import { findLocalSetupReference, findLocalSetupTypeReference } from '../flow-analysis';
import { getNodeRange } from './utils';

/**
 * Hoisted Vue macros run outside the generated Shopware setup callback.
 * Their runtime arguments must therefore stay independent from setup-local values.
 */
function assertHoistedMacroArgumentsDoNotUseLocalSetup({
    scriptOffset,
    localSetupNames,
    macroCalls,
}: {
    scriptOffset: number;
    localSetupNames: Set<string>;
    macroCalls: { name: string; call: CallExpression }[];
}): void {
    macroCalls.forEach(({ name, call }) => {
        // Both the value arguments and the type arguments (`defineProps<...>()`) are hoisted with the
        // call. Value arguments are scanned for value references; every hoisted node (arguments and type
        // parameters) is scanned for type references into a binding that stays inside the callback.
        const valueReference = call.arguments
            .map((argument) => findLocalSetupReference(argument, localSetupNames))
            .find(Boolean);
        const typeReference = (
            [
                ...call.arguments,
                call.typeParameters,
            ] as (BabelNode | null | undefined)[]
        )
            .map((hoistedNode) => findLocalSetupTypeReference(hoistedNode, localSetupNames))
            .find(Boolean);
        const reference = valueReference ?? typeReference;

        if (!reference) {
            return;
        }

        throw new ShopwareSetupTransformError(
            `${name}() arguments are hoisted outside the Shopware setup callback and must not reference local setup bindings. Use inline literals or imported constants instead.`,
            scriptOffset + getNodeRange(reference, scriptOffset).start,
        );
    });
}

export { assertHoistedMacroArgumentsDoNotUseLocalSetup };
