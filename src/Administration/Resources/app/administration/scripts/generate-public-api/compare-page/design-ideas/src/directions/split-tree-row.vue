<template>
    <button
        class="plain tree__row"
        :class="`is--${type}`"
        type="button"
        :style="{ '--depth': depth }"
        :aria-expanded="open"
        @click="emit('toggle')"
    >
        <span class="tree__chevron">
            <mt-icon
                :name="open ? 'regular-chevron-down-xs' : 'regular-chevron-right-xs'"
                size="12px"
            />
        </span>
        <component
            :is="type === 'unit' ? 'code' : 'span'"
            class="tree__name"
            :title="name"
        >{{ name }}</component>
        <span class="tree__counts">
            <template
                v-for="(count, index) in counts"
                :key="count.group"
            >
                <span v-if="index > 0"> · </span>
                <span :data-group="count.group">{{ count.label }}</span>
            </template>
        </span>
    </button>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */

/**
 * @sw-package framework
 *
 * One row of the tree of layout "Split" that opens and closes what is below it: a kind, a folder or a unit, with
 * the counts of its groups at the end.
 */
import { MtIcon } from '@shopware-ag/meteor-component-library';
import type { TreeCount, TreeNode } from '../shared/split-tree';

defineProps<{ type: TreeNode; name: string; depth: number; open: boolean; counts: TreeCount[] }>();
const emit = defineEmits<{ toggle: [] }>();
</script>

<style>
.tree__row {
    width: 100%;
    display: grid;
    grid-template-columns: 12px minmax(0, 1fr) auto;
    align-items: center;
    column-gap: 8px;
    height: 32px;
    padding: 0 var(--tree-inset) 0 calc(var(--tree-inset) + var(--depth) * var(--tree-step));
    line-height: var(--font-line-height-xs);
}

.tree__row:hover {
    background: var(--color-interaction-secondary-hover);
}

/*
 * The kind stays in view while its folders scroll, so the reader knows which kind a folder belongs to. The line
 * below it shows where the rows pass under it.
 */
.tree__row.is--kind {
    position: sticky;
    top: 0;
    z-index: 1;
    background: var(--color-background-primary-default);
    box-shadow: 0 1px 0 var(--color-border-primary-default);
}

.tree__row.is--kind:hover {
    background: var(--color-interaction-secondary-hover);
}

.tree__chevron {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 22px;
    color: var(--color-text-secondary-default);
}

/* A name keeps to one line and yields to the counts. Its full text is in the title. */
.tree .tree__name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    line-height: var(--font-line-height-xs);
}

.tree__row.is--folder .tree__name {
    color: var(--color-text-secondary-default);
}

.tree__row.is--kind .tree__name {
    font-weight: var(--font-weight-semibold);
}

/* Units of one folder often differ only in the end of their name, so the name of a unit wraps and is never cut. */
.tree__row.is--unit {
    align-items: start;
    height: auto;
    min-height: 32px;
    padding-block: 5px;
}

.tree .tree__row.is--unit .tree__name {
    font-weight: var(--font-weight-medium);
    white-space: normal;
    overflow-wrap: anywhere;
}

/* The counts are smaller than the name, and the offset puts both on one baseline. */
.tree__counts {
    position: relative;
    top: 1px;
    color: var(--color-text-secondary-default);
    font-size: var(--font-size-2xs);
    white-space: nowrap;
}

/* A count has the colour of its group and says the group in a word. The breaks are heavier as well. */
.tree__counts [data-group] {
    color: var(--group-text);
}

.tree__counts [data-group='Break'] {
    font-weight: var(--font-weight-semibold);
}
</style>
