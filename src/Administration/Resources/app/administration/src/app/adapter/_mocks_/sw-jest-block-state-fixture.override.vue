<template>
    <sw-block extends="sw_jest_block_state_fixture_content">
        <sw-block-parent />

        <span class="counter">{{ counter }}</span>
        <span class="doubled">{{ counter * 2 }}</span>
        <button
            class="increment"
            type="button"
            @click="counter++"
        >
            increment
        </button>
    </sw-block>
</template>

<script setup lang="ts">
import { ref } from 'vue';

const counter = ref(0);

swDefineOverride({});
</script>
