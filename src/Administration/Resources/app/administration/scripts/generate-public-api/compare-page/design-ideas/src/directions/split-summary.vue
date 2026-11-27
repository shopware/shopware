<template>
    <div class="summary">
        <header class="summary__head">
            <h2 class="summary__title">
                {{ breaks > 0 ? `${plural(breaks, 'break')} in ${plural(unitsWithBreaks, 'unit')}` : 'No breaks' }}
            </h2>
            <p
                v-if="breaks > 0"
                class="summary__muted"
            >
                A break has to be fixed or announced before the change can merge.
            </p>
        </header>

        <ul class="summary__groups">
            <li
                v-for="count in counts"
                :key="count.group"
                class="group-entry"
                :data-group="count.group"
            >
                <span class="group-entry__label">{{ GROUPS[count.group].label }}</span>
                <strong>{{ count.size.toLocaleString('en') }}</strong>
                <span>{{ GROUPS[count.group].meaning }}</span>
            </li>
        </ul>

        <p class="summary__muted">
            Pick an entry in the tree to see what changed, what a plugin runs into and how to fix it.
        </p>
        <p class="summary__muted summary__small">
            Compared {{ base.slice(0, 12) }} with {{ head.slice(0, 12) }}, with the generator of {{ GENERATOR }}.
            <template v-if="privateCount > 0">
                {{ plural(privateCount, 'change') }} to private elements are not listed. They are never public API.
            </template>
        </p>
    </div>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */

/**
 * @sw-package framework
 *
 * What the detail side of layout "Split" shows while no entry is picked: the verdict, and what each group means,
 * in the look the tree gives the entries of that group.
 */
import { computed } from 'vue';
import { plural } from '../shared/report';
import { GROUPS } from '../shared/split-tree';
import type { TreeCount } from '../shared/split-tree';
import { GENERATOR } from '../shared/states';

const props = defineProps<{
    counts: TreeCount[];
    unitsWithBreaks: number;
    base: string;
    head: string;
    privateCount: number;
}>();

const breaks = computed(() => props.counts.find(({ group }) => group === 'Break')?.size ?? 0);
</script>

<style>
.summary {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.summary__head {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.summary__title {
    font-size: var(--font-size-m);
    font-weight: var(--font-weight-semibold);
    line-height: var(--font-line-height-m);
}

.summary__muted {
    color: var(--color-text-secondary-default);
}

.summary__small {
    font-size: var(--font-size-2xs);
    line-height: var(--font-line-height-2xs);
}

.summary__groups {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.summary__groups > li {
    grid-template-columns: 52px 48px minmax(0, 1fr);
    align-items: baseline;
}

.summary__groups strong {
    font-weight: var(--font-weight-semibold);
    font-variant-numeric: tabular-nums;
    text-align: right;
}
</style>
