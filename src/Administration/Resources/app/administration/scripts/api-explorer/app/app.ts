/**
 * @sw-package framework
 */

import { computed, defineComponent, onMounted, ref, shallowRef, watch } from 'vue';
import {
    fetchCompare,
    fetchPullRequest,
    fetchState,
    type CompareResponse,
    type PullRequestResponse,
    type StateResponse,
} from './api';
import { AppHeader } from './components/app-header';
import { AppSidebar } from './components/app-sidebar';
import { CompareDialog } from './components/compare-dialog';
import { refLabel, WORKTREE_REF } from './model/format';
import { comparedPullRequest, describePullRequestError, pullRequestSideName } from './model/pull-request';
import { replaceComparisonInHash, setActiveComparison, useHashLocation } from './router';
import { readStaticReport } from './static-report';
import { BlocksView } from './views/blocks-view';
import { ComponentDetailView } from './views/component-detail-view';
import { ComponentsView } from './views/components-view';
import { OverviewView } from './views/overview-view';
import { ShopwareObjectView } from './views/shopware-object-view';

export const App = defineComponent({
    name: 'ApiExplorerApp',
    components: {
        AppHeader,
        AppSidebar,
        CompareDialog,
        OverviewView,
        BlocksView,
        ComponentsView,
        ComponentDetailView,
        ShopwareObjectView,
    },
    setup() {
        const location = useHashLocation();
        const route = computed(() => location.value.route);
        const initialComparison = location.value.comparison;
        // A static report has a fixed comparison and no server, so it never fetches anything.
        const report = readStaticReport();
        const state = shallowRef<StateResponse | null>(null);
        const result = shallowRef<CompareResponse | null>(
            report
                ? {
                      base: report.base,
                      head: { ...report.head, commit: report.head.commit ?? report.worktreeCommit },
                      diff: report.diff,
                      timings: { baseMs: 0, headMs: 0, diffMs: 0 },
                  }
                : null,
        );
        const base = ref(report?.base.ref ?? initialComparison?.base ?? 'trunk');
        const head = ref(report?.head.ref ?? initialComparison?.head ?? WORKTREE_REF);
        // Loading from the start, so the first paint already says what is being read.
        const loading = ref(report === null);

        if (report) {
            setActiveComparison(null);
        }
        const error = ref<string | null>(null);
        const errorTitle = ref('The comparison failed');
        const pullRequest = shallowRef<PullRequestResponse | null>(null);
        const fetchingPullRequest = ref(false);
        const loadedAt = ref<number | null>(null);
        const dialogOpen = ref(false);
        const query = ref('');
        let requestId = 0;

        const loadState = async (): Promise<void> => {
            try {
                state.value = await fetchState();
            } catch (stateError) {
                error.value = stateError instanceof Error ? stateError.message : String(stateError);
            }
        };

        // The service does not pass a refresh on to the pull request, so a refresh fetches it here first.
        const loadPullRequest = async (input: string, refresh: boolean, current: number): Promise<PullRequestResponse> => {
            fetchingPullRequest.value = true;

            try {
                return await fetchPullRequest(input, refresh);
            } catch (pullRequestError) {
                const message = pullRequestError instanceof Error ? pullRequestError.message : String(pullRequestError);

                if (current === requestId) {
                    errorTitle.value = 'The pull request could not be loaded';
                }

                throw new Error(describePullRequestError(message).text);
            } finally {
                if (current === requestId) {
                    fetchingPullRequest.value = false;
                }
            }
        };

        const compare = async (refresh = false): Promise<void> => {
            requestId += 1;
            const current = requestId;
            loading.value = true;
            error.value = null;
            const label = comparedPullRequest(base.value, head.value);

            try {
                const loaded = label ? await loadPullRequest(label.input, refresh, current) : null;

                if (current !== requestId) {
                    return;
                }

                pullRequest.value = loaded;

                errorTitle.value = 'The comparison failed';
                const response = await fetchCompare(base.value, head.value, refresh);

                if (current === requestId) {
                    result.value = response;
                    loadedAt.value = Date.now();
                }
            } catch (compareError) {
                if (current === requestId) {
                    error.value = compareError instanceof Error ? compareError.message : String(compareError);
                }
            } finally {
                if (current === requestId) {
                    loading.value = false;
                }
            }

            if (refresh) {
                void loadState();
            }
        };

        const syncUrl = (): void => {
            const comparison = { base: base.value, head: head.value };
            setActiveComparison(comparison);
            replaceComparisonInHash(comparison);
        };

        const compareWith = (nextBase: string, nextHead: string): Promise<void> => {
            base.value = nextBase;
            head.value = nextHead;
            syncUrl();

            return compare();
        };

        onMounted(async () => {
            if (report) {
                return;
            }

            if (initialComparison) {
                syncUrl();
                await Promise.all([
                    loadState(),
                    compare(),
                ]);
                return;
            }

            await loadState();
            await compareWith(state.value?.defaults.base ?? base.value, state.value?.defaults.head ?? head.value);
        });

        // A hash without refs (typed by hand) keeps the active comparison; one with other refs switches to them.
        watch(
            () => location.value.comparison,
            (comparison) => {
                if (report) {
                    return;
                }

                if (!comparison) {
                    syncUrl();
                } else if (comparison.base !== base.value || comparison.head !== head.value) {
                    void compareWith(comparison.base, comparison.head);
                }
            },
        );

        watch(route, () => window.scrollTo?.({ top: 0 }));

        const durationMs = computed(() => {
            const timings = result.value?.timings;

            return timings && !report ? timings.baseMs + timings.headMs + timings.diffMs : null;
        });

        const sideInfo = (refName: string, side: 'base' | 'head') => {
            const snapshot = result.value?.[side];

            return { ref: refName, commit: snapshot && snapshot.ref === refName ? snapshot.commit : null };
        };

        const loadingText = computed(() => {
            const label = comparedPullRequest(base.value, head.value);

            if (label && fetchingPullRequest.value) {
                return `Fetching ${label.number === null ? 'the PR' : `PR #${label.number}`} from GitHub …`;
            }

            const name = (refName: string): string => {
                const sideLabel = comparedPullRequest(refName, refName);

                return sideLabel ? pullRequestSideName(sideLabel, pullRequest.value) : refLabel(refName);
            };

            return `Reading ${name(base.value)} and ${name(head.value)} …`;
        });

        return {
            route,
            state,
            pullRequest,
            errorTitle,
            result,
            base,
            head,
            loading,
            error,
            loadedAt,
            dialogOpen,
            query,
            durationMs,
            generatedAt: report?.generatedAt ?? null,
            baseInfo: computed(() => sideInfo(base.value, 'base')),
            headInfo: computed(() => sideInfo(head.value, 'head')),
            loadingText,
            loadingHint: computed(() =>
                fetchingPullRequest.value
                    ? 'Fetching a pull request takes a few seconds.'
                    : 'The first read of a ref can take 10–30 s, of a pull request about a minute.',
            ),
            resultLabel: (refName: string) => refLabel(refName),
            refresh: () => compare(true),
            retry: () => compare(),
            swap: () => compareWith(head.value, base.value),
            apply: (selection: { base: string; head: string }) => {
                dialogOpen.value = false;
                void compareWith(selection.base, selection.head);
            },
        };
    },
    template: `
        <div class="app">
            <AppHeader
                v-model:query="query"
                :base="baseInfo"
                :head="headInfo"
                :uncommitted-files="state ? state.status.changedFiles : null"
                :loading="loading"
                :loaded-at="loadedAt"
                :duration-ms="durationMs"
                :generated-at="generatedAt"
                :pull-request="pullRequest"
                @open-compare="dialogOpen = true"
                @swap="swap"
                @refresh="refresh"
            />
            <div class="layout">
                <div class="layout__sidebar">
                    <AppSidebar :route="route" :result="result" />
                </div>
                <main class="layout__main" :aria-busy="loading ? 'true' : 'false'">
                    <div v-if="error" class="alert" role="alert">
                        <div>
                            <h2 class="alert__title">{{ errorTitle }}</h2>
                            <p class="alert__text">{{ error }}</p>
                            <p v-if="result" class="alert__text small">Below is the last successful comparison: {{ resultLabel(result.base.ref) }} against {{ resultLabel(result.head.ref) }}.</p>
                        </div>
                        <div class="toolbar">
                            <button type="button" class="button" @click="dialogOpen = true">Change refs</button>
                            <button type="button" class="button button--primary" @click="retry">Try again</button>
                        </div>
                    </div>

                    <div v-if="loading" class="loading" role="status">
                        <span class="spinner" aria-hidden="true"></span>
                        <div>
                            <p class="strong">{{ loadingText }}</p>
                            <p class="muted small">{{ loadingHint }}</p>
                        </div>
                    </div>

                    <template v-if="result">
                        <OverviewView v-if="route.name === 'overview'" :result="result" :query="query" :worktree="state ? state.status : null" :pull-request="pullRequest" />
                        <BlocksView v-else-if="route.name === 'blocks'" :result="result" :query="query" />
                        <ComponentsView v-else-if="route.name === 'components'" :result="result" :query="query" />
                        <ComponentDetailView v-else-if="route.name === 'component'" :key="route.component" :result="result" :name="route.component" :block="route.block" />
                        <ShopwareObjectView v-else-if="route.name === 'shopware'" :result="result" :path="route.path" :query="query" />
                    </template>
                </main>
            </div>

            <CompareDialog v-if="dialogOpen" :state="state" :base="base" :head="head" :pull-request="pullRequest" @apply="apply" @cancel="dialogOpen = false" />
        </div>
    `,
});
