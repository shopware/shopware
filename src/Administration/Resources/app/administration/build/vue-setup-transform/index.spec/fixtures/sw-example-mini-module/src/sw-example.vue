<template>
    <sw-block name="sw_example">
        <button :disabled="disabled" @click="save">
            {{ label }}
        </button>
        <slot name="actions" />
    </sw-block>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import type { SwExampleProps } from './sw-example.types';

defineProps<SwExampleProps>();
const emit = defineEmits<{ save: [count: number] }>();
const value = defineModel<string>();

const count = ref(0);

function save(): void {
    count.value += 1;
    emit('save', count.value);
}

swDefinePublic({ count, save, value });
</script>
