<template>
    <div class="tabbed">
        <mt-tabs
            :items="tabs"
            :default-item="active"
            @new-item-active="active = $event"
        />

        <p
            v-if="clusters.length === 0"
            class="tabbed__empty"
        >
            Nothing here for this comparison.
        </p>
        <div
            v-else
            class="tabbed__units"
        >
            <section
                v-for="cluster in shown"
                :key="cluster.unit.id"
                class="tabbed__unit"
            >
                <h3 class="tabbed__name">
                    <code-name :text="cluster.unit.name" />
                    <span
                        v-if="cluster.unit.kind !== 'component'"
                        class="tabbed__muted"
                    >{{ cluster.unit.kind }}</span>
                </h3>
                <div class="tabbed__items">
                    <div
                        v-for="line in cluster.lines"
                        :key="line.key"
                        class="tabbed__item"
                    >
                    <template v-if="line.type === 'fix'">
                        <p class="tabbed__title">
                            <code-name :text="line.title" />
                            <span class="tabbed__muted">{{ line.rule }}</span>
                        </p>
                        <fix-detail
                            :line="line"
                            :head="head"
                        />
                    </template>
                    <template v-else>
                        <p
                            v-if="line.label"
                            class="tabbed__title"
                        >
                            {{ line.label }}
                        </p>
                        <kind-names
                            :kinds="line.kinds"
                            :head="head"
                            :label="line.label"
                        />
                    </template>
                    </div>
                </div>
            </section>
        </div>

        <mt-button
            v-if="clusters.length > visible"
            class="tabbed__more"
            variant="secondary"
            @click="visible += PAGE"
        >
            Show {{ Math.min(PAGE, clusters.length - visible) }} more of {{ plural(clusters.length - visible, 'component') }}
        </mt-button>
    </div>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */

/**
 * @sw-package framework
 *
 * Layout "Tabs" of direction "One field": one tab per kind of change, and in it every component once with
 * everything about it visible.
 */
import { MtButton, MtTabs } from '@shopware-ag/meteor-component-library';
import { computed, ref, watch } from 'vue';
import { clustersOf } from '../shared/clusters';
import CodeName from '../shared/code-name.vue';
import FixDetail from '../shared/fix-detail.vue';
import KindNames from '../shared/kind-names.vue';
import { countBy, plural } from '../shared/report';
import type { Finding, FindingGroup } from '../shared/report';

const props = defineProps<{ findings: Finding[]; head: string }>();

/** A component shows everything about it, so a page holds few of them. */
const PAGE = 10;

const TAB_WORDS: Array<[group: FindingGroup, word: string]> = [
    [
        'Break',
        'Breaks',
    ],
    [
        'Addition',
        'New',
    ],
    [
        'Allowed',
        'Announced',
    ],
    [
        'Info',
        'Info',
    ],
];

const sizes = computed(() => countBy(props.findings, ({ group }) => group));

/** The tab of the breaks is always there, so a comparison without breaks says so. */
const tabs = computed(() =>
    TAB_WORDS.filter(([group]) => group === 'Break' || sizes.value.has(group)).map(([group, word]) => ({
        name: group,
        label: `${word} ${(sizes.value.get(group) ?? 0).toLocaleString('en')}`,
    })),
);

const active = ref<FindingGroup>(sizes.value.has('Break') ? 'Break' : (tabs.value.at(1)?.name ?? 'Break'));
const visible = ref(PAGE);
const clusters = computed(() => clustersOf(props.findings.filter(({ group }) => group === active.value)));
const shown = computed(() => clusters.value.slice(0, visible.value));

watch(
    [
        active,
        () => props.findings,
    ],
    () => {
        visible.value = PAGE;
    },
);
</script>

<style>
.tabbed {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* The tabs stay in view, so the reader always sees which kind of change the long list below shows. */
.tabbed .mt-tabs {
    position: sticky;
    top: 0;
    z-index: 1;
    background: var(--color-background-secondary-default);
}

.tabbed__units {
    background: var(--color-background-primary-default);
    border: 1px solid var(--color-border-primary-default);
    border-radius: var(--border-radius-xs);
    /* Clips the corners without becoming the box the sticky names hold on to. */
    overflow: clip;
}

.tabbed__unit + .tabbed__unit {
    border-top: 1px solid var(--color-border-primary-default);
}

.tabbed__unit {
    display: grid;
    grid-template-columns: 288px minmax(0, 1fr);
    column-gap: 24px;
    padding: 16px;
    font-size: var(--font-size-xs);
    line-height: var(--font-line-height-xs);
}

.tabbed__unit code {
    font-size: 1em;
    line-height: 1;
}

/* Names and titles are boxes of their own and may wrap, so they take the line height of the text again. */
.tabbed__unit .tabbed__name code,
.tabbed__unit .tabbed__title code {
    line-height: var(--font-line-height-xs);
}

/* Stays below the tabs while its items scroll by. */
.tabbed__name {
    position: sticky;
    top: 72px;
    align-self: start;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    align-content: start;
    gap: 0 8px;
    font-size: inherit;
    font-weight: var(--font-weight-semibold);
    overflow-wrap: anywhere;
}

.tabbed__items {
    min-width: 0;
}

.tabbed__item {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.tabbed__item + .tabbed__item {
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--color-border-primary-default);
}

/* The rule id keeps its place at the right edge, also beside a title that wraps. */
.tabbed__title {
    display: grid;
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
    column-gap: 12px;
    font-weight: var(--font-weight-medium);
}

.tabbed__muted {
    font-weight: var(--font-weight-regular);
    color: var(--color-text-secondary-default);
}

.tabbed__empty {
    font-size: var(--font-size-xs);
    line-height: var(--font-line-height-xs);
    color: var(--color-text-secondary-default);
}

.tabbed__more {
    align-self: flex-start;
}
</style>
