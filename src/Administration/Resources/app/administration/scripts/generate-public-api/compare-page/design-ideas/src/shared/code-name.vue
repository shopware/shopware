<template>
    <code><template
        v-for="(piece, index) in pieces"
        :key="index"
    >{{ piece }}<wbr></template></code>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */

/**
 * @sw-package framework
 *
 * A name of the code that may wrap after an underscore, a hyphen or a dot instead of in the middle of a word.
 * The breaks are elements, so a copied name stays the name.
 */
import { computed } from 'vue';

const props = defineProps<{ text: string }>();

const pieces = computed(() => props.text.split(/(?<=[_\-.])/));
</script>
