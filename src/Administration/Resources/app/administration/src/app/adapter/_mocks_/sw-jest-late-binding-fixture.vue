<template>
    <div>
        <button type="button" @click="save">save</button>
        <span class="label">{{ label }}</span>
        <span class="internal">{{ internal }}</span>
        <span class="persisted">{{ persisted.join(',') }}</span>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';

const persisted = ref<string[]>([]);
const label = computed(() => 'base');
const internal = computed(() => `internal sees ${label.value}`);

function persist(value: string) {
    persisted.value.push(`base:${value}`);
}

function save() {
    persist(label.value);
}

swDefinePublic({
    persisted,
    label,
    persist,
});
</script>
