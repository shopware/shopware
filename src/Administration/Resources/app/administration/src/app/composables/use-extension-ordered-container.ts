import { computed, Ref, ref } from "vue";
import { useCurrentExtensionId } from "../store/extension-context.store";


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
 * Warning message for when push() is called before flushByCurrentExtension() has been invoked.
 * This is to prevent duplicate or stale entries from appearing.
 * If extensionId is null, the push comes from shopware core context which doesn't need to be flushed
 */
const PUSH_BEFORE_FLUSH_WARNING =
    "[useExtensionOrderedContainer] push() was called before flushByCurrentExtension() has been invoked. " +
    "When using the Vite dev server (HMR), the store must register a flush command in flush-extension.init.ts " +
    "so that extension entries are cleared on re-execution. Otherwise, duplicate or stale entries may appear.";

export const useExtensionOrderedArray = <T>() => {
    const internalArray: Ref<T[]> = ref([]);
    const order: Ref<OrderItem[]> = ref([]);
    const hasBeenFlushed = ref(false);
    let hasWarnedPushBeforeFlush = false;

    // returns index to insert at
    const getOrderItem = (extensionId: string | null): { startIndex: number, nextInsertIndex: number, orderItem: OrderItem } => {
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
    }

    /**
     * pushes a value into the array preserving the order of the extensions (takes current extension from context)
     */
    const push = (value: T) => {
        const extensionId = useCurrentExtensionId();
        if (!hasBeenFlushed.value && !hasWarnedPushBeforeFlush && extensionId.value !== null) {
            console.log('extensionId', extensionId.value);
            hasWarnedPushBeforeFlush = true;
            console.log('push hasBeenFlushed', hasBeenFlushed.value);
            console.warn(PUSH_BEFORE_FLUSH_WARNING);
            console.trace();
        }
        const { nextInsertIndex, orderItem } = getOrderItem(extensionId.value);
        orderItem.count++;
        internalArray.value.splice(nextInsertIndex, 0, value);
    }

    /**
     * removes all entries for the current extension context from the array
     */
    const flushByCurrentExtension = () => {
        hasBeenFlushed.value = true;
        console.log('flush hasBeenFlushed', hasBeenFlushed.value);

        const extensionId = useCurrentExtensionId();
        console.log('flushByCurrentExtension', extensionId.value);
        const {startIndex, orderItem} = getOrderItem(extensionId.value);
        internalArray.value.splice(startIndex, orderItem.count);
        orderItem.count = 0;
    }

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

    return {
        items,
        push,
        flushByCurrentExtension,
        removeFirstWhere,
    };
}

export const useExtensionOrdereredArrayMap = <T>() => {
    const internalMap: Ref<Record<string, ReturnType<typeof useExtensionOrderedArray<T>>>> = ref({});

    const get = (key: string) => {
        if (!internalMap.value[key]) {
            internalMap.value[key] = useExtensionOrderedArray<T>();
        }
        return internalMap.value[key];
    }

    const flushByCurrentExtension = () => {
        Object.values(internalMap.value)
        .forEach(array => array.flushByCurrentExtension());
    }

    const clear = () => {
        internalMap.value = {};
    };

    const items = computed(() =>
        Object.freeze(
            Object.fromEntries(
                Object.entries(internalMap.value).map(([key, value]) => [key, value.items]),
            ) as Readonly<Record<string, ReturnType<typeof useExtensionOrderedArray<T>>["items"]>>,
        ),
    );

    return {
        items,
        flushByCurrentExtension,
        clear,
        get
    };
}