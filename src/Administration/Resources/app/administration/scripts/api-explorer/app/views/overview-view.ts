/**
 * @sw-package framework
 */

import { computed, defineComponent, ref, type PropType } from 'vue';
import type { CompareResponse, PullRequestResponse } from '../api';
import { ChangesPanel } from '../components/changes-panel';
import { DiagnosticsPanel } from '../components/diagnostics-panel';
import { formatCount, refLabel, shortSha, WORKTREE_REF } from '../model/format';
import { countNodes } from '../model/object-tree';
import {
    comparedPullRequest,
    parsePullRequestRefLabel,
    pullRequestSideName,
    pullRequestTitle,
    pullRequestUrl,
} from '../model/pull-request';
import type { ChangeFilter } from '../model/severity';

const PLANNED = [
    { title: 'Native blocks', detail: '<sw-block> and swDefinePublic from .vue files', tag: 'planned' },
    { title: 'Services', detail: 'DI container, Shopware.Service() and their methods', tag: 'planned' },
    { title: 'Stores', detail: 'Pinia state, getters and actions, plus Vuex (legacy)', tag: 'planned' },
    { title: 'Mixins and composables', detail: 'Shopware.Mixin and shopware:composables', tag: 'planned' },
    { title: 'Directives and filters', detail: 'Registered names and signatures', tag: 'planned' },
    { title: 'Modules and routes', detail: 'Module IDs, route names, route middleware', tag: 'planned' },
    { title: 'Extension SDK', detail: 'Position identifiers and data sets', tag: 'planned' },
    { title: 'Snippets and CSS classes', detail: 'Still open whether these count as public API', tag: 'idea' },
];

function plural(count: number, singular: string, pluralForm = `${singular}s`): string {
    return `${formatCount(count)} ${count === 1 ? singular : pluralForm}`;
}

export const OverviewView = defineComponent({
    name: 'OverviewView',
    components: { ChangesPanel, DiagnosticsPanel },
    props: {
        result: { type: Object as PropType<CompareResponse>, required: true },
        query: { type: String, default: '' },
        /** Branch and uncommitted file count for the description of the local state. */
        worktree: { type: Object as PropType<{ branch: string | null; changedFiles: number } | null>, default: null },
        pullRequest: { type: Object as PropType<PullRequestResponse | null>, default: null },
    },
    setup(props) {
        const filter = ref<ChangeFilter | null>(null);
        const summary = computed(() => props.result.diff.summary);

        const describe = (refName: string, commit: string | null): string => {
            if (refName === WORKTREE_REF) {
                const branch = props.worktree?.branch;
                const files = props.worktree ? ` with ${plural(props.worktree.changedFiles, 'uncommitted file')}` : '';

                return `Local state${branch ? ` on ${branch}` : ''}${files}`;
            }

            const pullRequestLabel = parsePullRequestRefLabel(refName);
            const name = pullRequestLabel ? pullRequestSideName(pullRequestLabel, props.pullRequest) : refName;

            return commit ? `${name} @ ${shortSha(commit)}` : name;
        };

        const pullRequestLink = computed(() => {
            const label = comparedPullRequest(props.result.base.ref, props.result.head.ref);

            if (!label) {
                return null;
            }

            const url = pullRequestUrl(label, props.pullRequest);

            return { text: pullRequestTitle(label.number, url ? props.pullRequest?.title : null), url };
        });

        const headText = computed(() => describe(props.result.head.ref, props.result.head.commit));
        const baseText = computed(() => describe(props.result.base.ref, props.result.base.commit));

        const coverage = computed(() => {
            const { head, diff } = props.result;
            const objectChanges = diff.changes.filter((change) => change.area === 'shopwareObject').length;
            const componentChanges = diff.changes.filter(
                (change) => change.area === 'component' && change.kind !== 'block',
            ).length;
            const blockChanges = diff.changes.filter((change) => change.kind === 'block').length;
            const privateComponents = head.components.filter((component) => component.visibility === 'private').length;
            const blocks = head.components.reduce((sum, component) => sum + component.blocks.length, 0);

            return [
                {
                    title: 'Shopware object',
                    detail: `${plural(countNodes(head.shopwareObject), 'entry', 'entries')} · ${plural(objectChanges, 'change')}`,
                },
                {
                    title: 'Components',
                    detail: `${plural(head.components.length, 'component')}, ${formatCount(privateComponents)} of them private · ${plural(componentChanges, 'change')}`,
                },
                {
                    title: 'Twig blocks',
                    detail: `${plural(blocks, 'block')} with hierarchy and content · ${plural(blockChanges, 'change')}`,
                },
            ];
        });

        const diagnostics = computed(() => [
            { side: refLabel(props.result.diff.base.ref), entries: props.result.diff.base.diagnostics },
            { side: refLabel(props.result.diff.head.ref), entries: props.result.diff.head.diagnostics },
        ]);

        return { filter, summary, pullRequestLink, headText, baseText, coverage, planned: PLANNED, diagnostics };
    },
    template: `
        <div class="page">
            <div class="page__header">
                <h1 class="page__title">Overview</h1>
                <p class="muted-strong"><template v-if="pullRequestLink"><a v-if="pullRequestLink.url" class="pr-link" :href="pullRequestLink.url" target="_blank" rel="noopener noreferrer">{{ pullRequestLink.text }}</a><span v-else class="strong">{{ pullRequestLink.text }}</span>: </template>{{ headText }}, compared to <code class="inline-code">{{ baseText }}</code>.</p>
            </div>

            <section v-if="summary.hasBreaking" class="banner banner--breaking" aria-labelledby="status-title">
                <div class="banner__text">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/></svg>
                    <div>
                        <h2 id="status-title" class="banner__title">{{ summary.bySeverity.breaking }} breaking {{ summary.bySeverity.breaking === 1 ? 'change' : 'changes' }} without deprecation</h2>
                        <p>The CI check turns red. Explain the changes in the PR or guard them with a deprecation or a feature flag.</p>
                    </div>
                </div>
                <button type="button" class="button button--danger" @click="filter = 'breaking'">Show breaking changes only</button>
            </section>
            <section v-else class="banner" aria-labelledby="status-title">
                <div class="banner__text">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                    <div>
                        <h2 id="status-title" class="banner__title">No breaking changes</h2>
                        <p>The CI check stays green. {{ summary.total }} {{ summary.total === 1 ? 'change' : 'changes' }} in total.</p>
                    </div>
                </div>
            </section>

            <DiagnosticsPanel :groups="diagnostics" />

            <ChangesPanel v-model:filter="filter" :changes="result.diff.changes" :query="query" />

            <section class="stack" aria-labelledby="coverage-title">
                <div class="stack stack--tight">
                    <h2 id="coverage-title" class="section-title">What the tool reads</h2>
                    <p class="muted small">The prototype reads the Shopware object and the components including their Twig blocks. The other categories are planned and already show up in the navigation.</p>
                </div>
                <div class="coverage">
                    <div v-for="card in coverage" :key="card.title" class="coverage__card" data-testid="coverage-card">
                        <div class="coverage__head"><span class="coverage__title">{{ card.title }}</span><span class="tag tag--read">read</span></div>
                        <span class="muted-strong small">{{ card.detail }}</span>
                    </div>
                    <div v-for="card in planned" :key="card.title" class="coverage__card coverage__card--planned">
                        <div class="coverage__head"><span class="coverage__title">{{ card.title }}</span><span class="tag">{{ card.tag }}</span></div>
                        <span class="muted-strong small">{{ card.detail }}</span>
                    </div>
                </div>
            </section>
        </div>
    `,
});
