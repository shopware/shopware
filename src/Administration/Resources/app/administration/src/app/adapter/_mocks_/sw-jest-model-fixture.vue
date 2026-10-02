<template>
    <input :value="modelValue" @input="modelValue = ($event.target as HTMLInputElement).value" />
    <button type="button" data-testid="default-model" @click="modelValue = `${modelValue ?? ''}!`">
        Update default model
    </button>
    <button type="button" data-testid="headline-model" @click="headline = `${headline}!`">{{ headline }}</button>
</template>

<script setup lang="ts">
const modelValue = defineModel<string>();
const headline = defineModel<string>('headline' as const, { default: 'untitled' });

swDefinePublic({
    modelValue,
});
</script>
