<template>
    <figure class="code-diff">
        <figcaption class="code-diff__file">
            <span>{{ block.file }}</span>
            <span class="code-diff__sizes">
                <span
                    v-if="removed > 0"
                    class="code-diff__removed"
                >−{{ removed }}</span>
                <span
                    v-if="added > 0"
                    class="code-diff__added"
                >+{{ added }}</span>
                <mt-button
                    v-if="copyable"
                    size="x-small"
                    variant="secondary"
                    @click="copy"
                >
                    {{ copied ? 'Copied' : 'Copy the new lines' }}
                </mt-button>
            </span>
        </figcaption>
        <div class="code-diff__scroll">
            <div
                class="code-diff__lines"
                :class="{ 'is--numbered': numbered }"
            >
                <template
                    v-for="(line, index) in block.lines"
                    :key="index"
                >
                    <div
                        class="code-diff__line"
                        :class="LINE_CLASS[line.type]"
                    >
                        <template v-if="numbered">
                            <span class="code-diff__number">{{ line.before ?? '' }}</span>
                            <span class="code-diff__number">{{ line.after ?? '' }}</span>
                        </template>
                        <span
                            class="code-diff__sign"
                            aria-hidden="true"
                        >{{ SIGN[line.type] }}</span>
                        <code class="code-diff__text">{{ line.text }}</code>
                    </div>
                    <div
                        v-if="line.note"
                        class="code-diff__note"
                    >
                        <p>{{ line.note }}</p>
                    </div>
                </template>
            </div>
        </div>
    </figure>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */

/**
 * @sw-package framework
 *
 * Code with its changes: removed and added lines are tinted and signed, both line numbers stand beside a line,
 * and a note stands below the line it is about.
 */
import { MtButton } from '@shopware-ag/meteor-component-library';
import { computed, ref } from 'vue';
import type { CodeBlock, DiffLine } from './detail-examples';

const props = defineProps<{ block: CodeBlock; copyable?: boolean }>();

const SIGN: Record<DiffLine['type'], string> = { ' ': '', '-': '−', '+': '+', '!': '!' };
const LINE_CLASS: Record<DiffLine['type'], string> = { ' ': '', '-': 'is--removed', '+': 'is--added', '!': 'is--marked' };

const removed = computed(() => props.block.lines.filter(({ type }) => type === '-').length);
const added = computed(() => props.block.lines.filter(({ type }) => type === '+').length);
const numbered = computed(() => props.block.lines.some(({ before, after }) => before !== undefined || after !== undefined));
const copied = ref(false);

function copy(): void {
    const text = props.block.lines
        .filter(({ type }) => type === '+')
        .map((line) => line.text)
        .join('\n');

    void navigator.clipboard?.writeText(text).catch(() => {
        // A page opened as a file may not get the clipboard; the lines stay selectable.
    });
    copied.value = true;
    setTimeout(() => {
        copied.value = false;
    }, 1500);
}
</script>

<style>
.code-diff {
    margin: 0;
    border: 1px solid var(--color-border-primary-default);
    border-radius: var(--border-radius-xs);
    overflow: hidden;
    font-size: var(--font-size-2xs);
    line-height: 20px;
}

/* The height counts the border below, so what is inside centres on whole pixels. */
.code-diff__file {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 33px;
    padding: 0 12px;
    background: var(--color-background-tertiary-default);
    border-bottom: 1px solid var(--color-border-primary-default);
    color: var(--color-text-secondary-default);
}

.code-diff__sizes {
    display: flex;
    align-items: center;
    gap: 8px;
    font-variant-numeric: tabular-nums;
}

/* The button keeps the width of its longer label, so the sizes beside it stay put while it says "Copied". */
.code-diff__sizes .mt-button {
    min-width: 134px;
}

.code-diff__removed {
    color: var(--color-red-600);
}

.code-diff__added {
    color: var(--color-green-700);
}

.code-diff__scroll {
    overflow-x: auto;
    background: var(--color-background-primary-default);
}

/*
 * One grid for all lines, so a tint reaches as far as the longest line when the code scrolls sideways. The room
 * below the last line is for the scrollbar that lies over the code. The first column holds the bar of a line and
 * starts its numbers where the file name starts.
 */
.code-diff__lines {
    --first-column: 23px;
    --second-column: 41px;

    display: grid;
    grid-template-columns: var(--first-column) auto;
    width: max-content;
    min-width: 100%;
    padding: 4px 0 12px;
}

.code-diff__lines.is--numbered {
    --first-column: 49px;

    grid-template-columns: var(--first-column) var(--second-column) 20px auto;
}

.code-diff__line,
.code-diff__note {
    grid-column: 1 / -1;
    display: grid;
    grid-template-columns: subgrid;
    background: var(--line-tint);
}

/* The numbers and the sign stay in view when the code scrolls sideways, so a line still says what it is. */
.code-diff__number,
.code-diff__sign {
    position: sticky;
    background: var(--line-tint, var(--color-background-primary-default));
    user-select: none;
}

.code-diff__line > :first-child {
    left: 0;
    border-left: 3px solid var(--line-bar, transparent);
}

.code-diff__number {
    padding: 0 8px 0 4px;
    text-align: right;
    color: var(--color-text-secondary-default);
    font-variant-numeric: tabular-nums;
}

.code-diff__number:first-child {
    padding-left: 9px;
}

.code-diff__number + .code-diff__number {
    left: var(--first-column);
}

.code-diff__number + .code-diff__sign {
    left: calc(var(--first-column) + var(--second-column));
}

.code-diff__sign {
    text-align: center;
    font-family: ui-monospace, "SF Mono", Menlo, Consolas, monospace;
}

.code-diff .code-diff__text {
    padding-right: 12px;
    font-size: 1em;
    line-height: 20px;
    white-space: pre;
}

.code-diff__line.is--removed {
    --line-tint: var(--color-background-critical-default);
    --line-bar: var(--color-border-critical-default);
}

.code-diff__line.is--added {
    --line-tint: var(--color-background-positive-default);
    --line-bar: var(--color-border-positive-default);
}

.code-diff__line.is--marked {
    --line-tint: var(--color-background-attention-default);
    --line-bar: var(--color-border-attention-default);
}

/* A note is a remark on the lines above it, not code: it starts where the code starts, in the type of the page. */
.code-diff__note {
    --line-tint: var(--color-background-tertiary-default);

    border-left: 3px solid var(--color-border-primary-default);
}

.code-diff__note p {
    grid-column: -2;
    position: sticky;
    left: 0;
    margin: 0;
    padding: 6px 12px 6px 0;
    font-family: var(--font-family-body);
    font-size: var(--font-size-xs);
    line-height: var(--font-line-height-xs);
    white-space: normal;
}
</style>
