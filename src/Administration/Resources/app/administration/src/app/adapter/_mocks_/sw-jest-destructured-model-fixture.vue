<template>
    <input v-model="note" class="note" />
    <span class="note-modifiers">{{ JSON.stringify(noteModifiers) }}</span>
</template>

<script setup lang="ts">
const [note, noteModifiers] = defineModel<string>('note');

swDefinePublic({
    note,
});
</script>
