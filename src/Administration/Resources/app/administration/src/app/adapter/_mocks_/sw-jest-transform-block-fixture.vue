<template>
    <div class="sw-jest-transform-block-fixture">
        <span class="base-counter">{{ counter }}</span>
        <sw-block name="sw_jest_transform_block_fixture_body">
            <span class="default-content">default</span>
        </sw-block>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';

const counter = ref(0);

swDefinePublic({
    counter,
});
</script>
