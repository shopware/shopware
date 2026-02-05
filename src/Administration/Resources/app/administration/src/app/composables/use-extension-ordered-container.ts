import { computed, Ref, ref } from 'vue';
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

export const useExtensionOrderedArray = <T>() => {
    const internalArray: Ref<T[]> = ref([]);
    const order: Ref<OrderItem[]> = ref([]);

    // returns index to insert at
    const getOrderItem = (
        extensionId: string | null,
    ): { startIndex: number; nextInsertIndex: number; orderItem: OrderItem } => {
        let index = 0;
        for (const item of order.value) {
            if (item.extensionId === extensionId) {
                return { startIndex: index, nextInsertIndex: index + item.count, orderItem: item };
            }
            index += item.count;
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
        const { nextInsertIndex, orderItem } = getOrderItem(extensionId.value);
        orderItem.count++;
        internalArray.value.splice(nextInsertIndex, 0, value);
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
        for (const item of order.value) {
            if (index < runningIndex + item.count) {
                item.count--;
                internalArray.value.splice(index, 1);
                return;
            }
            runningIndex += item.count;
        }
    };

    const items = computed(() => internalArray.value);

    const flushEventListener = (event: { src: string }) => {
        flushByExtension(event.src);
    };

    Shopware.Utils.EventBus.on('sw-extension-loaded', flushEventListener);

    const clear = () => {
        internalArray.value = [];
        order.value = [];
    };

    return {
        items,
        push,
        removeFirstWhere,
        clear,
    };
};

export const useExtensionOrdereredArrayMap = <T>() => {
    const internalMap: Ref<Record<string, ReturnType<typeof useExtensionOrderedArray<T>>>> = ref({});

    const get = (key: string) => {
        if (!internalMap.value[key]) {
            internalMap.value[key] = useExtensionOrderedArray<T>();
        }
        return internalMap.value[key];
    };

    const clear = () => {
        internalMap.value = {};
    };

    const items = computed(() =>
        Object.freeze(
            Object.fromEntries(
                Object.entries(internalMap.value).map(
                    ([
                        key,
                        value,
                    ]) => [
                        key,
                        value.items,
                    ],
                ),
            ) as Readonly<Record<string, ReturnType<typeof useExtensionOrderedArray<T>>['items']>>,
        ),
    );

    return {
        items,
        clear,
        get,
    };
};
