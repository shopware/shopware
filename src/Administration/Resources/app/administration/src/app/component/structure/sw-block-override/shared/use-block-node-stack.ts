/**
 * @sw-package framework
 * @private
 *
 * Shared render stack for block extension points.
 *
 * `sw-block` (native base templates) and `sw-native-block-host` (legacy Twig base templates) differ only
 * in *which* slots they stack; how the stack renders — last slot wins, everything below it becomes the
 * parent chain `<sw-block-parent />` pops from — is identical and lives here.
 */

import { computed, provide, ref, type ComputedRef, type Slot } from 'vue';
import parentsInjectionKey from '../sw-block/parents-injection-key';

/**
 * @private
 *
 * Renders the last slot of a block stack and provides the remaining ones as its parent chain.
 *
 * `resolveSlots` is called on every render so late-registered extensions (native override components
 * mount after the app root) are picked up reactively.
 *
 * @example
 * const template = useBlockNodeStack(() => [defaultSlot, ...getBlocks(name)], () => props.data);
 */
export default function useBlockNodeStack(
    resolveSlots: () => Slot[],
    resolveData: () => unknown,
): ComputedRef<ReturnType<Slot> | undefined> {
    const providedParents = ref<ReturnType<Slot>[]>([]);
    provide(parentsInjectionKey, providedParents);

    return computed(() => {
        const blockNodes = resolveSlots().map((slot) => slot?.(resolveData()));

        const lastNode = blockNodes.pop();
        // Each <sw-block-parent /> calls .pop() exactly once in its own setup()
        // to claim its parent slot. The array must be reset to the current render's
        // ordered list so that each parent instance pops the correct slot — not a
        // stale or accumulated list from a previous render cycle.
        providedParents.value = blockNodes;

        return lastNode;
    });
}
