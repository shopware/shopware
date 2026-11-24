/**
 * @sw-package framework
 */
import { computed, ref } from 'vue';

const items = ref<string[]>([]);
const discount = ref(0);
const total = computed((): number => items.value.length);
const isEmpty = computed(() => items.value.length === 0);

function addItem(item: string): void {
    items.value.push(item);
}

function clear(): void {
    items.value = [];
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function useExampleCart() {
    return { items, discount, total, isEmpty, addItem, clear };
}
