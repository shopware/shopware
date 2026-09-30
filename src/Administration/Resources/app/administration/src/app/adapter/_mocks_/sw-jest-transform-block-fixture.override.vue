<template>
    <sw-block extends="sw_jest_transform_block_fixture_body">
        <button type="button" :[labelAttribute]="'Increment'" @[eventName]="increment">
            {{ clicks }}
        </button>
    </sw-block>
</template>

<script setup>
import { ref } from 'vue';

const eventName = 'click';
const labelAttribute = 'aria-label';
const clicks = ref(0);
const increment = () => {
    clicks.value += 1;
};

swDefineOverride({});
</script>
