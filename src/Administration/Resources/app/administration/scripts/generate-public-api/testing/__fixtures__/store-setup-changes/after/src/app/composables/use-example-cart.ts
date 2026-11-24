/**
 * @sw-package framework
 */
import { computed, ref } from 'vue';

const items = ref<string[]>([]);
const total = computed((): number => items.value.length);

function addItem(item: string, quantity: number): void {
    items.value.push(...Array<string>(quantity).fill(item));
}

function clear(): void {
    items.value = [];
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default function useExampleCart() {
    return { items, total, addItem, clear };
}
