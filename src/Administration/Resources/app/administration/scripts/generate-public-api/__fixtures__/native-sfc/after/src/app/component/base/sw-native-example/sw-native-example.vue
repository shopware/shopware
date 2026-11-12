<template>
    <sw-block name="sw_native_example">
        <button type="button" @click="emit('save', count)">
            <sw-block name="sw_native_example_button_label">{{ displayedLabel }}</sw-block>: {{ count }}
        </button>
    </sw-block>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';

const props = withDefaults(defineProps<{ label?: string }>(), { label: 'Counter' });
const emit = defineEmits<{ save: [value: number] }>();
const displayedLabel = computed(() => props.label);
const count = ref(1);

swDefinePublic({ count });
</script>
