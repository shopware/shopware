<template>
    <div class="split">
        <div class="split__list">
            <section
                v-for="cluster in shown"
                :key="cluster.unit.id"
                class="split__unit"
            >
                <h3 class="split__name">
                    <code-name :text="cluster.unit.name" />
                    <span
                        v-if="cluster.unit.kind !== 'component'"
                        class="split__muted"
                    >{{ cluster.unit.kind }}</span>
                </h3>
                <button
                    v-for="line in cluster.lines"
                    :key="line.key"
                    class="plain split__line"
                    type="button"
                    :aria-current="isSelected(cluster, line)"
                    @click="select(cluster, line)"
                >
                    <mt-status-dot
                        :variant="DOT_VARIANT[line.group]"
                        size="s"
                    />
                    <template v-if="line.type === 'fix'">
                        <code-name :text="line.title" />
                        <span class="split__muted">{{ line.rule }}</span>
                    </template>
                    <span
                        v-else
                        class="split__summary"
                    >{{ summaryOf(line) }}</span>
                </button>
            </section>
            <mt-button
                v-if="clusters.length > visible"
                class="split__more"
                variant="secondary"
                @click="visible += PAGE"
            >
                Show {{ Math.min(PAGE, clusters.length - visible) }} more of {{ plural(clusters.length - visible, 'component') }}
            </mt-button>
        </div>

        <aside
            v-if="selected"
            class="split__detail"
            aria-live="polite"
        >
            <template v-if="selected.line.type === 'fix'">
                <header class="split__head">
                    <code-name
                        class="split__muted"
                        :text="selected.cluster.unit.name"
                    />
                    <h3 class="split__title">
                        <code-name :text="selected.line.title" />
                    </h3>
                    <p class="split__muted">
                        {{ selected.line.group }} · {{ selected.line.rule }}
                    </p>
                </header>
                <fix-detail
                    :line="selected.line"
                    :head="head"
                />
            </template>
            <template v-else>
                <header class="split__head">
                    <code-name
                        class="split__muted"
                        :text="selected.cluster.unit.name"
                    />
                    <h3 class="split__title">
                        {{ summaryOf(selected.line) }}
                    </h3>
                </header>
                <p
                    v-if="selected.line.group === 'Addition'"
                    class="split__note"
                >
                    Plugins can rely on these from now on. Mark them @private if they are not meant for plugins.
                </p>
                <kind-names
                    :kinds="selected.line.kinds"
                    :head="head"
                    :label="selected.line.label"
                />
            </template>
        </aside>
    </div>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */

/**
 * @sw-package framework
 *
 * Layout "Split" of direction "One field": one short line per entry on the left, and what the selected entry
 * says in full beside it.
 */
import { MtButton, MtStatusDot } from '@shopware-ag/meteor-component-library';
import { computed, ref, shallowRef, watch } from 'vue';
import { clustersOf } from '../shared/clusters';
import type { Cluster, ClusterLine, NamesLine } from '../shared/clusters';
import CodeName from '../shared/code-name.vue';
import FixDetail from '../shared/fix-detail.vue';
import KindNames from '../shared/kind-names.vue';
import { plural } from '../shared/report';
import type { Finding, FindingGroup } from '../shared/report';

const props = defineProps<{ findings: Finding[]; head: string }>();

const PAGE = 40;

const DOT_VARIANT: Record<FindingGroup, 'critical' | 'positive' | 'attention' | 'info' | 'neutral'> = {
    Break: 'critical',
    Addition: 'positive',
    Allowed: 'attention',
    Info: 'info',
    Private: 'neutral',
};

const visible = ref(PAGE);
const clusters = computed(() => clustersOf(props.findings));
const shown = computed(() => clusters.value.slice(0, visible.value));

function summaryOf(line: NamesLine): string {
    const size = line.kinds.reduce((sum, { findings }) => sum + findings.length, 0);

    return `${size.toLocaleString('en')} ${line.label ?? 'new'}`;
}

const picked = shallowRef<{ unit: string; line: string } | null>(null);

/** The entry the reader picked, or the first one of the report while it is still there. */
const selected = computed((): { cluster: Cluster; line: ClusterLine } | null => {
    const cluster = clusters.value.find(({ unit }) => unit.id === picked.value?.unit) ?? clusters.value.at(0);
    const line = cluster?.lines.find(({ key }) => key === picked.value?.line) ?? cluster?.lines.at(0);

    return cluster === undefined || line === undefined ? null : { cluster, line };
});

function select(cluster: Cluster, line: ClusterLine): void {
    picked.value = { unit: cluster.unit.id, line: line.key };
}

function isSelected(cluster: Cluster, line: ClusterLine): boolean {
    return selected.value?.cluster.unit.id === cluster.unit.id && selected.value.line.key === line.key;
}

watch(
    () => props.findings,
    () => {
        visible.value = PAGE;
    },
);
</script>

<style>
.split {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 668px;
    align-items: start;
    gap: 24px;
    font-size: var(--font-size-xs);
    line-height: var(--font-line-height-xs);
}

.split code {
    font-size: 1em;
    line-height: 1;
}

.split__list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.split__unit {
    display: flex;
    flex-direction: column;
}

/* The names and the dots start where the text of the fields above starts. */
.split__name {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 17px 4px;
    font-size: inherit;
    font-weight: var(--font-weight-semibold);
    overflow-wrap: anywhere;
}

.split__line {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr) auto;
    align-items: center;
    column-gap: 8px;
    padding: 5px 17px;
    border-radius: 0 var(--border-radius-xs) var(--border-radius-xs) 0;
    overflow-wrap: anywhere;
}

.split__summary {
    grid-column: 2 / -1;
}

/* Titles and names are boxes of their own and may wrap, so they take the line height of the text again. */
.split .split__line code,
.split .split__name code,
.split .split__title code {
    line-height: inherit;
}

.split__line:hover {
    background: var(--color-interaction-secondary-hover);
}

.split__line[aria-current='true'] {
    background: var(--color-background-primary-default);
    box-shadow: inset 2px 0 0 var(--color-border-brand-selected);
}

.split__muted,
.split__note {
    color: var(--color-text-secondary-default);
}

.split__more {
    align-self: flex-start;
}

/* Stays in view while the list scrolls, above the review bar of the design ideas. */
.split__detail {
    position: sticky;
    top: 16px;
    max-height: calc(100vh - 104px);
    overflow: auto;
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 16px;
    background: var(--color-background-primary-default);
    border: 1px solid var(--color-border-primary-default);
    border-radius: var(--border-radius-xs);
}

/* What the entry is, set apart from what it says. */
.split__head {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.split__title {
    font-size: var(--font-size-s);
    font-weight: var(--font-weight-semibold);
    line-height: var(--font-line-height-s);
    overflow-wrap: anywhere;
}
</style>
