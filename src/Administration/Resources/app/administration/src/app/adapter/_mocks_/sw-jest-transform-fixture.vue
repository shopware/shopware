<template>
    <button type="button" @click="emit('save', count)">{{ displayedLabel }}: {{ count }}</button>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';

const props = withDefaults(
    defineProps<{
        label?: string;
    }>(),
    {
        label: 'Counter',
    },
);
const emit = defineEmits<{
    save: [value: number];
}>();
const displayedLabel = computed(() => props.label);
const count = ref(1);

swDefinePublic({
    count,
});
</script>
