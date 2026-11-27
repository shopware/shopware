<template>
    <mt-data-table
        ref="table"
        class="tabled"
        :class="{ 'is--one-page': rows.length <= limit }"
        layout="full"
        :data-source="pageRows"
        :columns="COLUMNS"
        :is-loading="false"
        :current-page="page"
        :pagination-limit="limit"
        :pagination-total-items="rows.length"
        :pagination-options="[25, 50, 100]"
        :number-of-results="rows.length"
        :sort-by="sortBy"
        :sort-direction="sortDirection"
        :filters="[]"
        :applied-filters="[]"
        disable-search
        disable-settings-table
        disable-edit
        disable-delete
        @pagination-current-page-change="onPage"
        @pagination-limit-change="onLimit"
        @sort-change="onSort"
    >
        <template #column-unit="{ data }">
            <code-name
                class="tabled__name"
                :text="data.unit"
            />
        </template>
        <template #column-breaks="{ data }">
            <ul
                v-if="data.fixes.length > 0"
                class="tabled__fixes"
            >
                <li
                    v-for="line in data.fixes"
                    :key="line.key"
                >
                    <button
                        class="plain tabled__fix"
                        type="button"
                        :aria-expanded="opened.has(data.id + line.key)"
                        @click="toggle(data.id + line.key)"
                    >
                        <mt-status-dot
                            :variant="line.group === 'Break' ? 'critical' : 'attention'"
                            size="s"
                        />
                        <span class="tabled__title">
                            <code-name :text="line.title" />
                            <span class="tabled__rule">{{ line.rule }}</span>
                        </span>
                    </button>
                    <fix-detail
                        v-if="opened.has(data.id + line.key)"
                        class="tabled__detail"
                        :line="line"
                        :head="head"
                    />
                </li>
            </ul>
            <span
                v-else
                class="tabled__none"
            >–</span>
        </template>
        <template #column-additions="{ data }">
            <kind-names
                v-if="data.additions"
                :kinds="data.additions.kinds"
                :head="head"
            />
            <span
                v-else
                class="tabled__none"
            >–</span>
        </template>
        <template #column-info="{ data }">
            <div
                v-for="line in data.info"
                :key="line.key"
                class="tabled__info"
            >
                <span class="tabled__label">{{ line.label }}</span>
                <kind-names
                    :kinds="line.kinds"
                    :head="head"
                    :label="line.label"
                />
            </div>
            <span
                v-if="data.info.length === 0"
                class="tabled__none"
            >–</span>
        </template>
    </mt-data-table>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */

/**
 * @sw-package framework
 *
 * Layout "Table" of direction "One field": one row per component with a column per kind of change.
 */
import { MtDataTable, MtStatusDot } from '@shopware-ag/meteor-component-library';
import { computed, nextTick, ref, shallowRef, watch } from 'vue';
import { clustersOf } from '../shared/clusters';
import type { FixLine, NamesLine } from '../shared/clusters';
import CodeName from '../shared/code-name.vue';
import FixDetail from '../shared/fix-detail.vue';
import KindNames from '../shared/kind-names.vue';
import type { Finding } from '../shared/report';

const props = defineProps<{ findings: Finding[]; head: string }>();

interface Row {
    id: string;
    unit: string;
    /** Breaks and announced changes, each with its fix. */
    fixes: FixLine[];
    breaks: number;
    additions: NamesLine | undefined;
    info: NamesLine[];
}

const COLUMNS = [
    { label: 'Component', property: 'unit', position: 0, width: 230, sortable: true, renderer: 'text' as const, cellWrap: 'normal' as const },
    { label: 'Breaks', property: 'breaks', position: 1, width: 330, sortable: true, renderer: 'text' as const, cellWrap: 'normal' as const },
    { label: 'New', property: 'additions', position: 2, width: 370, renderer: 'text' as const, cellWrap: 'normal' as const },
    { label: 'Info', property: 'info', position: 3, width: 290, renderer: 'text' as const, cellWrap: 'normal' as const },
];

const page = ref(1);
const limit = ref(25);
const sortBy = ref('breaks');
const sortDirection = ref<'ASC' | 'DESC'>('DESC');

const rows = computed((): Row[] => {
    const all = clustersOf(props.findings).map(({ unit, breaks, lines }) => ({
        id: unit.id,
        unit: unit.name,
        fixes: lines.filter((line) => line.type === 'fix'),
        breaks,
        additions: lines.find((line): line is NamesLine => line.type === 'names' && line.group === 'Addition'),
        info: lines.filter((line): line is NamesLine => line.type === 'names' && line.group === 'Info'),
    }));
    const direction = sortDirection.value === 'ASC' ? 1 : -1;

    return sortBy.value === 'unit'
        ? all.sort((one, other) => direction * one.unit.localeCompare(other.unit))
        : all.sort((one, other) => direction * (one.breaks - other.breaks) || one.unit.localeCompare(other.unit));
});

const pageRows = computed(() => rows.value.slice((page.value - 1) * limit.value, page.value * limit.value));

const table = ref<{ $el: HTMLElement } | null>(null);

/** A page is long, so the next one starts at its top instead of where the last one was left. */
function onPage(value: number): void {
    page.value = value;
    void nextTick(() => table.value?.$el.scrollIntoView({ block: 'start' }));
}

function onLimit(value: number): void {
    limit.value = value;
    page.value = 1;
}

function onSort(property: string, direction: 'ASC' | 'DESC'): void {
    sortBy.value = property;
    sortDirection.value = direction;
}

watch(
    () => props.findings,
    () => {
        page.value = 1;
    },
);

const opened = shallowRef(new Set<string>());

function toggle(key: string): void {
    const next = new Set(opened.value);

    if (!next.delete(key)) {
        next.add(key);
    }

    opened.value = next;
}
</script>

<style>
.tabled {
    margin-bottom: 0;
    font-size: var(--font-size-xs);
    line-height: var(--font-line-height-xs);
}

/* The columns keep their width when a break opens its fix. */
.tabled table {
    table-layout: fixed;
}

/* One page needs no paging. */
.tabled.is--one-page .mt-card__footer {
    display: none;
}

.tabled code {
    font-size: 1em;
    line-height: 1;
}

.tabled td {
    vertical-align: top;
}

.tabled__name {
    font-weight: var(--font-weight-semibold);
    overflow-wrap: anywhere;
}

.tabled__fixes {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.tabled__fix {
    display: grid;
    grid-template-columns: auto minmax(0, 1fr);
    align-items: baseline;
    column-gap: 8px;
    text-align: left;
    overflow-wrap: anywhere;
}

/* The titles are boxes of their own and may wrap, so they take the line height of the text again. */
.tabled .tabled__fix code,
.tabled .tabled__name {
    line-height: var(--font-line-height-xs);
}

.tabled__rule {
    margin-left: 8px;
    color: var(--color-text-secondary-default);
}

/* A cell is narrow, so a kind stands above its names and the places get more room between them. */
.tabled .kind-names {
    grid-template-columns: minmax(0, 1fr);
    row-gap: 0;
}

.tabled .kind-names dd + dt {
    margin-top: 4px;
}

.tabled .fix-places {
    gap: 8px;
}

.tabled__fix:hover code {
    text-decoration: underline;
}

.tabled__detail {
    margin: 8px 0 8px 14px;
}

.tabled__info + .tabled__info {
    margin-top: 8px;
}

/* A cell without content would fall back to the text of the row, so it says that there is nothing. */
.tabled__label,
.tabled__none {
    color: var(--color-text-secondary-default);
}
</style>
