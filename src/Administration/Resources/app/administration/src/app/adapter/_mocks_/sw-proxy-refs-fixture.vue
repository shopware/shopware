<template>
    <div class="base">
        <sw-block name="sw_proxy_refs_block">
            <span class="title">{{ title }}</span>
        </sw-block>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';

const props = defineProps<{
    heading: string;
}>();
const title = ref(props.heading);

swDefinePublic({
    title,
});
</script>
