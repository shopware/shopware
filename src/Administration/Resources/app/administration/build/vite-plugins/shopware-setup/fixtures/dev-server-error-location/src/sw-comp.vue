<template>
    <p>{{ label }}</p>
</template>
<script setup>
import { computed } from 'vue';

const label = computed(() => 'Hello from source');

const broken = { a: 1 b: 2 };

swDefinePublic({ label, broken });
</script>
