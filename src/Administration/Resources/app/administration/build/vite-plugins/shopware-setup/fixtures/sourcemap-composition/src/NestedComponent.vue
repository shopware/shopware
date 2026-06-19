<template>
    <sw-block name="sw_example_card">
        <p>{{ headline }}</p>
    </sw-block>
</template>
<script setup>
import { computed } from 'vue';

const headline = computed(() => 'Hello from source');

swDefinePublic({
    headline,
});
</script>
