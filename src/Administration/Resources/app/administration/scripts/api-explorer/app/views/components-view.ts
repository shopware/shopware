/**
 * @sw-package framework
 */

import { computed, defineComponent, ref, watch, type PropType } from 'vue';
import type { CompareResponse } from '../api';
import { SegmentedToggle, SeverityPill } from '../components/common';
import { componentRows, directoryOf, registrationLabel, type ComponentRow } from '../model/components';
import { formatCount, matchesQuery } from '../model/format';
import { countByPill } from '../model/severity';
import { hrefFor } from '../router';

const PAGE_SIZE = 200;

export const ComponentsView = defineComponent({
    name: 'ComponentsView',
    components: { SeverityPill, SegmentedToggle },
    props: {
        result: { type: Object as PropType<CompareResponse>, required: true },
        query: { type: String, default: '' },
    },
    setup(props) {
        const localQuery = ref('');
        const mode = ref<'changed' | 'all'>('changed');
        const showPrivate = ref(true);
        const limit = ref(PAGE_SIZE);

        const allRows = computed(() => componentRows(props.result.base, props.result.head, props.result.diff));
        const changedCount = computed(() => allRows.value.filter((row) => row.changes.length > 0).length);
        const privateCount = computed(
            () => props.result.head.components.filter((component) => component.visibility === 'private').length,
        );

        // A row whose changes are not all private stays visible, even for a component that is private now.
        const isHiddenPrivate = (row: ComponentRow): boolean =>
            (row.head ?? row.base)?.visibility === 'private' && row.changes.every((change) => change.severity === 'private');

        const filteredBeforePrivate = computed(() =>
            allRows.value.filter(
                (row) =>
                    (mode.value === 'all' || row.changes.length > 0) &&
                    matchesQuery(row.name, props.query) &&
                    matchesQuery(row.name, localQuery.value),
            ),
        );
        const rows = computed(() => filteredBeforePrivate.value.filter((row) => showPrivate.value || !isHiddenPrivate(row)));
        const hiddenPrivate = computed(() => filteredBeforePrivate.value.length - rows.value.length);

        const visibleRows = computed(() =>
            rows.value.slice(0, limit.value).map((row) => {
                const entry = (row.head ?? row.base)!;

                return {
                    name: row.name,
                    removed: row.head === null,
                    href: hrefFor({ name: 'component', component: row.name, block: null }),
                    path: directoryOf(entry.sourceFile),
                    registration: registrationLabel(entry),
                    packageName: entry.packageName ?? '—',
                    visibility: entry.visibility,
                    deprecated: entry.deprecation !== null,
                    chips: countByPill(row.changes),
                };
            }),
        );

        const caption = computed(() =>
            mode.value === 'changed'
                ? `${formatCount(changedCount.value)} of ${formatCount(allRows.value.length)} components have changes`
                : `${formatCount(allRows.value.length)} components`,
        );

        watch(
            [
                mode,
                showPrivate,
                localQuery,
                () => props.query,
            ],
            () => {
                limit.value = PAGE_SIZE;
            },
        );

        return {
            localQuery,
            mode,
            showPrivate,
            rows,
            visibleRows,
            caption,
            hiddenPrivate,
            total: computed(() => formatCount(props.result.head.components.length)),
            privateCount: computed(() => formatCount(privateCount.value)),
            modes: [
                { value: 'changed', label: 'Changed only' },
                { value: 'all', label: 'All' },
            ],
            showMore: () => {
                limit.value += PAGE_SIZE;
            },
        };
    },
    template: `
        <div class="page">
            <div class="page__header">
                <h1 class="page__title">Components</h1>
                <p class="muted-strong">{{ total }} registered components, {{ privateCount }} of them marked <code class="inline-code">@private</code>. For each component: props, emits, slots, data, computed, methods, inject, mixins, extends and the Twig block tree.</p>
            </div>

            <div class="filters">
                <label class="search search--wide">
                    <span class="visually-hidden">Filter components</span>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input v-model="localQuery" type="search" placeholder="Filter components, e.g. sw-product">
                </label>
                <SegmentedToggle v-model="mode" :options="modes" />
                <label class="checkbox"><input v-model="showPrivate" type="checkbox">Show private</label>
            </div>

            <section class="card">
                <div class="card__header">
                    <span class="card__title card__title--small">{{ caption }}</span>
                    <span class="muted small">sorted by severity</span>
                </div>
                <div class="table-scroll">
                    <table class="table table--components">
                        <thead>
                            <tr><th scope="col">Component</th><th scope="col">Registration</th><th scope="col">Package</th><th scope="col">Visibility</th><th scope="col">Changes</th></tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in visibleRows" :key="row.name" data-testid="component-row">
                                <td>
                                    <div class="stack stack--tight">
                                        <a :href="row.href" class="mono strong" :class="{ removed: row.removed }">{{ row.name }}</a>
                                        <span class="mono path">{{ row.path }}</span>
                                    </div>
                                </td>
                                <td class="mono small">{{ row.registration }}</td>
                                <td>{{ row.packageName }}</td>
                                <td><span class="visibility" :data-visibility="row.visibility">{{ row.visibility }}</span></td>
                                <td>
                                    <div class="chips">
                                        <SeverityPill v-for="chip in row.chips" :key="chip.key" :change="chip.change" :count="chip.count" />
                                        <span v-if="row.chips.length === 0 && row.deprecated" class="pill" data-severity="newlyDeprecated"><span class="pill__symbol" aria-hidden="true">↓</span>deprecated</span>
                                        <span v-else-if="row.chips.length === 0" class="pill pill--neutral">unchanged</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <p v-if="rows.length === 0" class="empty">No components match this filter.</p>
                    <p v-if="hiddenPrivate > 0" class="table-note" data-testid="private-hidden">{{ hiddenPrivate }} private {{ hiddenPrivate === 1 ? 'component' : 'components' }} with only private changes hidden</p>
                    <div v-if="rows.length > visibleRows.length" class="more">
                        <button type="button" class="button button--small" @click="showMore">Show more ({{ rows.length - visibleRows.length }} left)</button>
                    </div>
                </div>
            </section>
        </div>
    `,
});
