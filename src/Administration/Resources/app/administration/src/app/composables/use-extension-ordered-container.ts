/**
 * @sw-package framework
 */

import type { Ref } from 'vue';
import { computed, ref, shallowRef, triggerRef } from 'vue';
import { useCurrentExtensionId } from '../store/extension-context.store';

interface OrderItem {
    /**
     * `null` means shopware core context
     */
    extensionId: string | null;

    /**
     * Number of items in the array for the extensionId
     */
    count: number;
}

/**
 * @private
 */
export const useExtensionOrderedArray = <T>() => {
    const internalArray: Ref<T[]> = ref([]);
    const order: Ref<OrderItem[]> = ref([]);

    // returns index to insert at
    const getOrderItem = (
        extensionId: string | null,
    ): { startIndex: number; nextInsertIndex: number; orderItem: OrderItem } => {
        let index = 0;
        const matching = order.value.find((item) => {
            if (item.extensionId === extensionId) {
                return true;
            }
            index += item.count;
            return false;
        });
        if (matching) {
            return { startIndex: index, nextInsertIndex: index + matching.count, orderItem: matching };
        }
        const item = { extensionId, count: 0 };
        order.value.push(item);
        return { startIndex: index, nextInsertIndex: index, orderItem: item };
    };

    /**
     * pushes a value into the array preserving the order of the extensions (takes current extension from context)
     */
    const push = (value: T) => {
        const extensionId = useCurrentExtensionId();
        const id = extensionId.value ?? null;
        const { nextInsertIndex, orderItem } = getOrderItem(id);
        internalArray.value.splice(nextInsertIndex, 0, value);
        orderItem.count += 1;
    };

    /**
     * removes all entries for the given extension
     */
    const flushByExtension = (extensionId: string | null) => {
        const { startIndex, orderItem } = getOrderItem(extensionId);
        internalArray.value.splice(startIndex, orderItem.count);
        orderItem.count = 0;
    };

    /**
     * removes the first entry that matches the predicate from the array (from any extension).
     * Updates the order count for the segment the item belonged to.
     */
    const removeFirstWhere = (predicate: (item: T) => boolean) => {
        const index = internalArray.value.findIndex(predicate);
        if (index === -1) {
            return;
        }
        let runningIndex = 0;
        const segment = order.value.find((item) => {
            if (index < runningIndex + item.count) {
                return true;
            }
            runningIndex += item.count;
            return false;
        });
        if (segment) {
            segment.count -= 1;
            internalArray.value.splice(index, 1);
        }
    };

    const items = computed(() => Object.freeze([...internalArray.value]));

    const flushEventListener = (event: { src: string }) => {
        flushByExtension(event.src);
    };

    Shopware.Utils.EventBus.on('sw-extension-loaded', flushEventListener);

    const dispose = () => {
        Shopware.Utils.EventBus.off('sw-extension-loaded', flushEventListener);
    };

    const reset = () => {
        internalArray.value = [];
        order.value = [];
    };

    return {
        items,
        push,
        removeFirstWhere,
        reset,
        dispose,
    };
};

/**
 * @private
 */
export const useExtensionOrdereredArrayMap = <T>() => {
    const internalMap: Ref<Map<string, ReturnType<typeof useExtensionOrderedArray<T>>>> = shallowRef(new Map());

    const get = (key: string) => {
        let entry = internalMap.value.get(key);
        if (!entry) {
            entry = useExtensionOrderedArray<T>();
            internalMap.value.set(key, entry);
            triggerRef(internalMap);
        }
        return entry;
    };

    const reset = () => {
        Array.from(internalMap.value.values()).forEach((entry) => entry.dispose());
        internalMap.value.clear();
        triggerRef(internalMap);
    };

    const items = computed(() =>
        Object.freeze(
            Object.fromEntries(
                Array.from(internalMap.value.entries()).map(
                    ([
                        key,
                        value,
                    ]) => [
                        key,
                        value.items.value,
                    ],
                ),
            ),
        ),
    );

    const dispose = () => {
        reset();
    };

    return {
        items,
        reset,
        get,
        dispose,
    };
};
