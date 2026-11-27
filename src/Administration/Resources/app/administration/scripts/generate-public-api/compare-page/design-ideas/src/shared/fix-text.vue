<template>
    <span class="fix-text">
        <template
            v-for="(piece, index) in fixText.pieces"
            :key="index"
        >
            <span
                v-if="piece.code"
                class="fix-text__marker"
            >
                <code>{{ piece.text }}</code>
                <mt-button
                    v-if="copyable"
                    class="fix-text__copy"
                    size="x-small"
                    variant="secondary"
                    square
                    ghost
                    :aria-label="copied === index ? 'Copied' : 'Copy the marker'"
                    @click="copy(piece.text, index)"
                >
                    <mt-icon
                        :name="copied === index ? 'regular-checkmark-xs' : 'regular-copy-s'"
                        size="12px"
                    />
                </mt-button>
            </span>
            <template v-else>{{ piece.text }}</template>
        </template>
    </span>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */
 
/**
 * @sw-package framework
 *
 * The text of a fix with its markers set as code, each with a button that copies it.
 */
import { MtButton, MtIcon } from '@shopware-ag/meteor-component-library';
import { computed, ref } from 'vue';
import { fixTextOf } from './report';

const props = withDefaults(defineProps<{ fix: string; copyable?: boolean }>(), { copyable: true });

/** A full stop after a marker would wrap to a line of its own, and the marker ends the sentence well enough. */
const fixText = computed(() => {
    const { pattern, pieces } = fixTextOf(props.fix);

    return { pattern, pieces: pieces.filter(({ text }, index) => !(text.trim() === '.' && pieces[index - 1]?.code)) };
});
const copied = ref<number | null>(null);

function copy(text: string, index: number): void {
    void navigator.clipboard?.writeText(text).catch(() => {
        // A page opened as a file may not get the clipboard; the marker stays selectable in the text.
    });
    copied.value = index;
    setTimeout(() => {
        copied.value = null;
    }, 1500);
}
</script>

<style>
/* A marker stays in one piece with its button and never reaches beyond its column. */
.fix-text__marker {
    display: inline-block;
    max-width: 100%;
}

.fix-text__marker code {
    padding: 1px 4px;
    border-radius: var(--border-radius-xs);
    background: var(--color-background-tertiary-default);
    overflow-wrap: anywhere;
    /* A marker that wraps keeps its padding on every line. */
    -webkit-box-decoration-break: clone;
    box-decoration-break: clone;
}

/* The button is taller than the text, and the negative margins keep it from making its line taller. */
.fix-text__copy {
    vertical-align: middle;
    margin: -4px 0 -4px 2px;
    border-color: transparent;
}
</style>
