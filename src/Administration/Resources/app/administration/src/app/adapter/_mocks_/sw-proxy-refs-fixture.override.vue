<template>
    <sw-block extends="sw_proxy_refs_block">
        <sw-block-parent />
        <button
            class="direct"
            type="button"
            @click="clicks++"
        >{{ shout }} {{ clicks }}</button>
        <proxy-refs-wrapper>
            <span class="nested">{{ clicks }}</span>
            <button
                class="nested-button"
                type="button"
                @click="clicks += 10"
            >nested</button>
        </proxy-refs-wrapper>
        <input
            v-model="note"
            class="note"
        >
        <span class="note-echo">{{ note }}</span>
        <span class="public">{{ title }}</span>
        <proxy-refs-formatter :format="(value) => `${value} x${clicks}`" />
        <button
            class="async"
            type="button"
            @click="async () => { await null; clicks += 100; }"
        >async</button>
        <button
            class="reset"
            type="button"
            @click="reset"
        >reset</button>
    </sw-block>
</template>

<script setup>
import { computed, ref } from 'vue';

const previousState = useSwPreviousState();
const clicks = ref(0);
const note = ref('');
const shout = computed(() => `${previousState.title.value}!`);
const title = computed(() => previousState.title.value.toUpperCase());
const reset = () => {
    clicks.value = 0;
};

swDefineOverride({
    title,
});
</script>
