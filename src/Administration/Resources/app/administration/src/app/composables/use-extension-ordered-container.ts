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


export const useExtensionOrderedArray = <T>() => {
    const internalArray: Ref<T[]> = ref([]);
    const order: Ref<OrderItem[]> = ref([]);

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
        const { nextInsertIndex, orderItem } = getOrderItem(extensionId.value);
        orderItem.count++;
        internalArray.value.splice(nextInsertIndex, 0, value);
    }

    /**
     * removes all entries for the current extension context from the array
     */
    const flushByCurrentExtension = () => {
        const extensionId = useCurrentExtensionId();
        const {startIndex, orderItem} = getOrderItem(extensionId.value);
        internalArray.value.splice(startIndex, orderItem.count);
        orderItem.count = 0;
    }

    const items = computed(() => internalArray.value);

    return {
        items,
        push,
        flushByCurrentExtension
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
        get
    };
}