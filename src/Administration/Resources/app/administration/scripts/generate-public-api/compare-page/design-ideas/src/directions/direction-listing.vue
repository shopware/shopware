<template>
    <main class="listing">
        <header class="listing__head">
            <mt-text
                as="h1"
                size="xl"
                weight="semibold"
            >
                Public API check
            </mt-text>
        </header>

        <mt-card
            title="Comparison"
            :subtitle="report ? `${report.base.slice(0, 12)} → ${report.head.slice(0, 12)}, computed with the generator of ${GENERATOR}` : undefined"
        >
            <form
                class="listing__form"
                @submit.prevent="submit"
            >
                <mt-text-field
                    v-model="pullRequest"
                    class="listing__pr"
                    label="Pull request"
                    placeholder="20959"
                    @update:model-value="clearRevisions"
                />
                <span class="listing__or">or</span>
                <mt-text-field
                    v-model="base"
                    label="Base"
                    placeholder="v6.7.0.0"
                    @update:model-value="pullRequest = ''"
                />
                <mt-text-field
                    v-model="head"
                    label="Head"
                    placeholder="trunk"
                    @update:model-value="pullRequest = ''"
                />
                <mt-button
                    type="submit"
                    variant="primary"
                    size="large"
                    :is-loading="state.kind === 'loading'"
                >
                    Compare
                </mt-button>
            </form>
            <div
                v-if="state.kind === 'loading'"
                class="listing__below"
            >
                <mt-progress-bar
                    :label="progress.message"
                    :model-value="progress.loaded"
                    :max-value="progress.total"
                />
            </div>
            <div
                v-if="state.kind === 'error'"
                class="listing__below"
            >
                <mt-banner
                    :variant="state.reason === 'limit' ? 'attention' : 'critical'"
                    :title="state.message"
                >
                    {{
                        state.reason === 'limit'
                            ? 'Add a GitHub token below to lift the limit, or wait.'
                            : 'Check the number of the pull request.'
                    }}
                </mt-banner>
                <form
                    v-if="state.reason === 'limit'"
                    class="listing__form"
                    @submit.prevent="emit('compare', state.request)"
                >
                    <mt-password-field
                        v-model="token"
                        label="GitHub token"
                        help-text="It stays in this tab and is only sent to api.github.com."
                    />
                    <mt-button
                        type="submit"
                        variant="secondary"
                        size="large"
                    >
                        Compare with token
                    </mt-button>
                </form>
            </div>
        </mt-card>

        <mt-banner
            v-if="report"
            :variant="breakCount > 0 ? 'critical' : 'positive'"
            :title="breakCount > 0 ? `${plural(breakCount, 'unannounced break')}` : 'No breaks'"
        >
            {{ countsText }}
        </mt-banner>

        <mt-data-table
            v-if="report || state.kind === 'loading'"
            class="listing__table"
            title="Findings"
            :subtitle="report ? `${plural(filtered.length, 'finding')} of ${report.findings.length.toLocaleString('en')}` : ''"
            layout="full"
            :data-source="rows"
            :columns="COLUMNS"
            :is-loading="!report"
            :current-page="page"
            :pagination-limit="limit"
            :pagination-total-items="filtered.length"
            :pagination-options="[25, 50, 100]"
            :search-value="query"
            :filters="FILTERS"
            :applied-filters="appliedFilters"
            :number-of-results="filtered.length"
            :sort-by="sortBy"
            :sort-direction="sortDirection"
            disable-settings-table
            disable-edit
            disable-delete
            @search-value-change="onSearch"
            @update:applied-filters="onFilters"
            @pagination-current-page-change="page = $event"
            @pagination-limit-change="onLimit"
            @sort-change="onSort"
            @open-details="open"
        >
            <template #column-at="{ data }">
                <mt-link
                    v-if="data.at && report"
                    as="a"
                    type="external"
                    target="_blank"
                    :to="sourceUrl(report.head, data.at)"
                >
                    {{ data.short }}
                </mt-link>
            </template>
        </mt-data-table>

        <mt-modal-root
            :is-open="opened !== null"
            @change="(isOpen: boolean) => !isOpen && (opened = null)"
        >
            <mt-modal
                v-if="opened && report"
                :title="memberText(opened)"
                :subtitle="`${unitOf(opened).kind} ${unitOf(opened).name} · ${opened.rule}`"
                width="m"
            >
                <div class="listing__detail">
                    <mt-badge :variant="BADGE_VARIANT[opened.group]">
                        {{ opened.group }}
                    </mt-badge>
                    <p>
                        {{ opened.explanation }}
                        <template v-if="stateNote(opened)">
                            Holds {{ stateNote(opened) }}.
                        </template>
                    </p>
                    <p v-if="opened.fix">
                        <strong>Fix:</strong> <fix-text :fix="opened.fix" />
                    </p>
                    <p v-if="opened.possibleRename">
                        Possibly renamed to <code>{{ opened.possibleRename }}</code>.
                    </p>
                    <p v-if="opened.alsoAffects">
                        Also affects {{ opened.alsoAffects.join(', ') }}.
                    </p>
                    <mt-link
                        v-if="opened.at"
                        as="a"
                        type="external"
                        target="_blank"
                        :to="sourceUrl(report.head, opened.at)"
                    >
                        {{ opened.at }}
                    </mt-link>
                </div>
            </mt-modal>
        </mt-modal-root>
    </main>
</template>

<script setup lang="ts">/* eslint-disable sw-core-rules/valid-shopware-setup -- a page of the design ideas, not a component of the Administration */
 
/**
 * @sw-package framework
 *
 * Direction "Listing": the findings as an Administration list with search, a group filter, sorting and pages.
 */
import {
    MtBadge,
    MtBanner,
    MtButton,
    MtCard,
    MtDataTable,
    MtLink,
    MtModal,
    MtModalRoot,
    MtPasswordField,
    MtProgressBar,
    MtText,
    MtTextField,
} from '@shopware-ag/meteor-component-library';
import { computed, ref, shallowRef } from 'vue';
import FixText from '../shared/fix-text.vue';
import {
    BADGE_VARIANT,
    GROUP_ORDER,
    countBy,
    matches,
    memberText,
    plural,
    shortAt,
    sourceUrl,
    stateNote,
    unitOf,
} from '../shared/report';
import type { Finding, FindingGroup } from '../shared/report';
import { GENERATOR } from '../shared/states';
import type { CompareRequest, Progress, ViewState } from '../shared/states';

const props = defineProps<{ state: ViewState; progress: Progress }>();
const emit = defineEmits<{ compare: [request: CompareRequest] }>();

const request = props.state.kind === 'start' ? null : props.state.request;
const pullRequest = ref(request !== null && 'pullRequest' in request ? String(request.pullRequest) : '');
const base = ref(request !== null && 'base' in request ? request.base : '');
const head = ref(request !== null && 'head' in request ? request.head : '');
const token = ref('');

function clearRevisions(): void {
    base.value = '';
    head.value = '';
}

function submit(): void {
    if (pullRequest.value.trim() !== '') {
        emit('compare', { pullRequest: Number(pullRequest.value.replace(/\D/g, '')) });
    } else if (base.value.trim() !== '' && head.value.trim() !== '') {
        emit('compare', { base: base.value.trim(), head: head.value.trim() });
    }
}

const report = computed(() => (props.state.kind === 'report' ? props.state.report : null));
const findings = computed(() => report.value?.findings ?? []);
const counts = computed(() => countBy(findings.value, ({ group }) => group));
const breakCount = computed(() => counts.value.get('Break') ?? 0);
const countsText = computed(() =>
    GROUP_ORDER.filter((group) => group !== 'Break' && counts.value.has(group))
        .map((group) => plural(counts.value.get(group) ?? 0, group.toLowerCase(), group === 'Addition' ? 'additions' : group.toLowerCase()))
        .join(' · '),
);

const COLUMNS = [
    {
        label: 'Group',
        property: 'group',
        position: 0,
        width: 120,
        sortable: true,
        renderer: 'badge' as const,
        rendererOptions: {
            renderItemBadge: (data: unknown) => {
                const group = (data as { group: FindingGroup }).group;
                // The badge renderer of the data table takes its own variant names, not those of MtBadge.
                const variants = { Break: 'critical', Addition: 'positive', Allowed: 'warning', Info: 'info', Private: 'default' } as const;

                return { label: group, variant: variants[group] };
            },
        },
    },
    { label: 'Component, service or store', property: 'unit', position: 1, width: 280, sortable: true, renderer: 'text' as const },
    { label: 'Element', property: 'member', position: 2, width: 420, sortable: true, renderer: 'text' as const },
    { label: 'Rule', property: 'rule', position: 3, width: 200, sortable: true, renderer: 'text' as const },
    { label: 'Source', property: 'at', position: 4, width: 260, renderer: 'text' as const },
];

const FILTERS = [
    {
        id: 'group',
        label: 'Group',
        type: { id: 'options', options: GROUP_ORDER.map((group) => ({ id: group, label: group })) },
    },
];

/** Private changes are never public API, so the list starts without them. */
const DEFAULT_FILTER = {
    ...FILTERS[0],
    type: { ...FILTERS[0].type, options: FILTERS[0].type.options.filter(({ id }) => id !== 'Private') },
};

const query = ref('');
const appliedFilters = shallowRef<(typeof FILTERS)[number][]>([DEFAULT_FILTER]);
const page = ref(1);
const limit = ref(25);
const sortBy = ref('group');
const sortDirection = ref<'ASC' | 'DESC'>('ASC');

function onSearch(value: string): void {
    query.value = value;
    page.value = 1;
}

function onFilters(filters: (typeof FILTERS)[number][]): void {
    appliedFilters.value = filters;
    page.value = 1;
}

function onLimit(value: number): void {
    limit.value = value;
    page.value = 1;
}

function onSort(property: string, direction: 'ASC' | 'DESC'): void {
    sortBy.value = property;
    sortDirection.value = direction;
}

interface Row {
    id: string;
    group: FindingGroup;
    unit: string;
    member: string;
    rule: string;
    at: string;
    short: string;
    finding: Finding;
}

const allRows = computed<Row[]>(() =>
    findings.value.map((finding, index) => ({
        id: String(index),
        group: finding.group,
        unit: unitOf(finding).name,
        member: memberText(finding),
        rule: finding.rule,
        at: finding.at ?? '',
        short: finding.at === undefined ? '' : shortAt(finding.at),
        finding,
    })),
);

const filtered = computed(() => {
    const groups = appliedFilters.value.find(({ id }) => id === 'group')?.type.options.map(({ id }) => id);

    return allRows.value
        .filter((row) => groups === undefined || groups.includes(row.group))
        .filter((row) => matches(row.finding, query.value));
});

const rows = computed(() => {
    const key = sortBy.value as keyof Row;
    const order = (row: Row): string =>
        key === 'group' ? String(GROUP_ORDER.indexOf(row.group)) : String(row[key]);
    const sorted = [...filtered.value].sort(
        (one, other) => order(one).localeCompare(order(other)) * (sortDirection.value === 'ASC' ? 1 : -1),
    );

    return sorted.slice((page.value - 1) * limit.value, page.value * limit.value);
});

const opened = shallowRef<Finding | null>(null);

function open(row: Row): void {
    opened.value = row.finding;
}
</script>

<style>
.listing {
    max-width: 1400px;
    margin: 0 auto;
    padding: 32px 24px;
    display: flex;
    flex-direction: column;
    gap: 24px;
}

.listing .mt-card {
    margin: 0;
    max-width: none;
}

.listing__form {
    display: flex;
    align-items: flex-end;
    flex-wrap: wrap;
    gap: 16px;
}

.listing__form .mt-field {
    width: 220px;
}

.listing__pr.mt-field {
    width: 140px;
}

.listing__or {
    padding-bottom: 14px;
    color: var(--color-text-secondary-default);
}

.listing__below {
    margin-top: 24px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.listing__detail {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 12px;
}
</style>
