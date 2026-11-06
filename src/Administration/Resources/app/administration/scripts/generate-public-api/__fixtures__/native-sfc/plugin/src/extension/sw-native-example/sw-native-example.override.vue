<template>
    <sw-block extends="sw_native_example_label">
        <sw-block-parent />
        <span class="swag-native-example-badge">{{ badge }}</span>
    </sw-block>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const previousState = useSwPreviousState<ComponentPublicApiMapping['sw-native-example']>();
const badge = computed(() => `x${previousState.count.value}`);

function increment(step: number): void {
    previousState.increment(step * 2);
}

swDefineOverride({ increment });
</script>
