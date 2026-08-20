<template>
    <sw-block extends="sw_jest_bridge_fixture_content">
        <sw-block-parent />
        <div class="native-extension">{{ headline }}</div>
    </sw-block>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const previousState = useSwPreviousState();
const headline = computed(() => `${previousState.headline.value}!`);

swDefineOverride({
    headline,
});
</script>
