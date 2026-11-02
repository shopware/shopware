<template>
    <sw-block extends="sw_jest_transform_block_fixture_body">
        <button class="dynamic-button" type="button" :[labelAttribute]="'Increment'" @[eventName]="increment">
            {{ clicks }}
        </button>

        <span class="counter">{{ counter }}</span>
        <button class="increment" type="button" @click="counter++">increment</button>
        <input v-model.number="counter" class="counter-input" />

        <span class="clicks">{{ clicks }}</span>
        <button class="private-increment" type="button" @click="clicks++">count click</button>

        <span class="label">{{ label }}</span>
        <input v-model="label" class="label-input" />
    </sw-block>
</template>

<script setup>
import { ref } from 'vue';

const eventName = 'click';
const labelAttribute = 'aria-label';
const counter = ref(10);
const clicks = ref(0);
const label = ref('initial');
const increment = () => {
    clicks.value += 1;
};

swDefineOverride({
    counter,
});
</script>
