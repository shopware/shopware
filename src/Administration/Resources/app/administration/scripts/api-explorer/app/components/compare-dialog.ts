/**
 * @sw-package framework
 */

import { computed, defineComponent, nextTick, onBeforeUnmount, onMounted, ref, shallowRef, type PropType } from 'vue';
import { fetchPullRequest, type PullRequestResponse, type StateResponse } from '../api';
import { shortSha, WORKTREE_REF } from '../model/format';
import { comparedPullRequest, describePullRequestError, pullRequestNumber, pullRequestState } from '../model/pull-request';

const MAX_TAGS = 6;
const RELEASE_BRANCH = /^\d+\.\d+\.x$/;
const CUSTOM = '__custom__';

export const CompareDialog = defineComponent({
    name: 'CompareDialog',
    props: {
        state: { type: Object as PropType<StateResponse | null>, default: null },
        base: { type: String, required: true },
        head: { type: String, required: true },
        /** The pull request of the current comparison, shown until another one is loaded. */
        pullRequest: { type: Object as PropType<PullRequestResponse | null>, default: null },
    },
    emits: ['apply', 'cancel'],
    setup(props, { emit }) {
        const refs = computed(() => props.state?.refs ?? []);
        const suggestions = computed(() => refs.value.filter((entry) => entry.kind !== 'remote'));
        const baseOptions = computed(() => {
            const currentBranch = props.state?.status.branch ?? null;
            const defaultBase = props.state?.defaults.base ?? 'trunk';
            const branches = refs.value.filter(
                (entry) =>
                    entry.kind === 'branch' &&
                    (entry.name === defaultBase || entry.name === currentBranch || RELEASE_BRANCH.test(entry.name)),
            );
            const rank = (name: string): number => {
                if (name === defaultBase) {
                    return 0;
                }

                return name === currentBranch ? 1 : 2;
            };
            branches.sort((a, b) => rank(a.name) - rank(b.name));
            const tags = refs.value.filter((entry) => entry.kind === 'tag').slice(0, MAX_TAGS);

            return [
                ...branches,
                ...tags,
            ].map((entry) => {
                let meta = `${entry.kind === 'tag' ? 'Tag' : 'Branch'} · ${shortSha(entry.commit)}`;

                if (entry.name === currentBranch) {
                    meta += ' · current branch, last commit without uncommitted changes';
                }

                return { value: entry.name, label: entry.name, meta };
            });
        });
        const known = (value: string): boolean => baseOptions.value.some((option) => option.value === value);

        const baseChoice = ref(known(props.base) ? props.base : CUSTOM);
        const baseCustom = ref(known(props.base) ? '' : props.base);
        const headChoice = ref(props.head === WORKTREE_REF ? WORKTREE_REF : CUSTOM);
        const headCustom = ref(props.head === WORKTREE_REF ? '' : props.head);

        const baseValue = computed(() => (baseChoice.value === CUSTOM ? baseCustom.value.trim() : baseChoice.value));
        const headValue = computed(() => (headChoice.value === CUSTOM ? headCustom.value.trim() : WORKTREE_REF));
        const canApply = computed(() => baseValue.value !== '' && headValue.value !== '');

        const worktreeMeta = computed(() => {
            const status = props.state?.status;

            if (!status) {
                return 'The working tree including uncommitted changes';
            }

            return `${status.branch ?? 'detached HEAD'} with ${status.changedFiles} uncommitted ${status.changedFiles === 1 ? 'file' : 'files'}`;
        });

        const currentPullRequest = comparedPullRequest(props.base, props.head);
        const pullRequestInput = ref(currentPullRequest?.input ?? '');
        const loadedPullRequest = shallowRef<PullRequestResponse | null>(
            currentPullRequest && props.pullRequest?.number === currentPullRequest.number ? props.pullRequest : null,
        );
        const pullRequestLoading = ref<string | null>(null);
        const pullRequestError = ref<{ text: string; detail: string | null } | null>(null);
        let pullRequestRequest = 0;

        const loadPullRequest = async (): Promise<void> => {
            const input = pullRequestInput.value.trim();
            pullRequestError.value = null;

            if (input === '') {
                pullRequestError.value = describePullRequestError('Invalid pull request');
                return;
            }

            pullRequestRequest += 1;
            const current = pullRequestRequest;
            const number = pullRequestNumber(input);
            pullRequestLoading.value = `Fetching ${number === null ? 'the PR' : `PR #${number}`} …`;
            loadedPullRequest.value = null;

            try {
                const loaded = await fetchPullRequest(input);

                if (current === pullRequestRequest) {
                    loadedPullRequest.value = loaded;
                }
            } catch (error) {
                if (current === pullRequestRequest) {
                    pullRequestError.value = describePullRequestError(
                        error instanceof Error ? error.message : String(error),
                    );
                }
            } finally {
                if (current === pullRequestRequest) {
                    pullRequestLoading.value = null;
                }
            }
        };

        const dialog = ref<HTMLElement | null>(null);
        const onKeydown = (event: KeyboardEvent): void => {
            if (event.key === 'Escape') {
                emit('cancel');
            }
        };
        onMounted(() => {
            window.addEventListener('keydown', onKeydown);
            void nextTick(() => dialog.value?.querySelector<HTMLElement>('input:checked, input')?.focus());
        });
        onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

        return {
            CUSTOM,
            WORKTREE_REF,
            dialog,
            suggestions,
            baseOptions,
            baseChoice,
            baseCustom,
            headChoice,
            headCustom,
            canApply,
            worktreeMeta,
            pullRequestInput,
            loadedPullRequest,
            pullRequestLoading,
            pullRequestError,
            loadPullRequest,
            shortSha,
            stateOf: pullRequestState,
            comparePullRequest: () => {
                if (loadedPullRequest.value) {
                    emit('apply', { ...loadedPullRequest.value.refs });
                }
            },
            apply: () => {
                if (canApply.value) {
                    emit('apply', { base: baseValue.value, head: headValue.value });
                }
            },
        };
    },
    template: `
        <div class="overlay" @click.self="$emit('cancel')">
            <form ref="dialog" class="dialog" role="dialog" aria-modal="true" aria-labelledby="compare-title" @submit.prevent="apply">
                <div class="dialog__header">
                    <h2 id="compare-title" class="dialog__title">Comparison settings</h2>
                    <button type="button" class="icon-button" aria-label="Close" @click="$emit('cancel')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                    </button>
                </div>

                <fieldset class="fieldset pull-request" aria-labelledby="pull-request-legend">
                    <legend id="pull-request-legend" class="fieldset__legend">Pull request <span class="muted normal">what a GitHub PR changes</span></legend>
                    <div class="pull-request__row">
                        <input v-model="pullRequestInput" type="text" class="text-input mono" placeholder="19666, #19666 or a GitHub link" aria-label="Pull request" @keydown.enter.prevent="loadPullRequest">
                        <button type="button" class="button" :disabled="pullRequestLoading !== null" @click="loadPullRequest">Load</button>
                    </div>
                    <p v-if="pullRequestLoading" class="muted small" role="status">{{ pullRequestLoading }}</p>
                    <p v-if="pullRequestError" class="pull-request__error" role="alert">
                        {{ pullRequestError.text }}
                        <span v-if="pullRequestError.detail" class="muted small mono"><br>{{ pullRequestError.detail }}</span>
                    </p>
                    <div v-if="loadedPullRequest" class="pull-request__card" data-testid="pull-request-card">
                        <span class="pull-request__title">PR #{{ loadedPullRequest.number }} · {{ loadedPullRequest.title ?? 'Title unknown' }}</span>
                        <span class="pull-request__facts">
                            <span class="tag" :class="'tag--' + stateOf(loadedPullRequest).split(' ')[0]">{{ stateOf(loadedPullRequest) }}</span>
                            <span>into <span class="mono">{{ loadedPullRequest.baseRef }}</span></span>
                            <span>head <span class="mono">{{ shortSha(loadedPullRequest.headSha) }}</span></span>
                            <span>merge base <span class="mono">{{ shortSha(loadedPullRequest.mergeBaseSha) }}</span></span>
                        </span>
                        <ul v-if="loadedPullRequest.warnings.length > 0" class="pull-request__warnings">
                            <li v-for="warning in loadedPullRequest.warnings" :key="warning">{{ warning }}</li>
                        </ul>
                        <div class="toolbar">
                            <button type="button" class="button button--primary button--small" @click="comparePullRequest">Compare this PR</button>
                        </div>
                    </div>
                </fieldset>

                <fieldset class="fieldset">
                    <legend class="fieldset__legend">Base <span class="muted normal">what you compare against</span></legend>
                    <label v-for="option in baseOptions" :key="option.value" class="option">
                        <input v-model="baseChoice" type="radio" name="base" :value="option.value">
                        <span class="option__text">
                            <span class="option__label mono">{{ option.label }}</span>
                            <span class="option__meta">{{ option.meta }}</span>
                        </span>
                    </label>
                    <label class="option option--custom">
                        <input v-model="baseChoice" type="radio" name="base" :value="CUSTOM" aria-label="Custom ref">
                        <span class="option__inline">Custom ref</span>
                        <input v-model="baseCustom" type="text" class="text-input mono" list="explorer-refs" placeholder="Branch, tag or commit" aria-label="Custom base ref" @focus="baseChoice = CUSTOM">
                    </label>
                </fieldset>

                <fieldset class="fieldset">
                    <legend class="fieldset__legend">Compare <span class="muted normal">what gets checked</span></legend>
                    <label class="option">
                        <input v-model="headChoice" type="radio" name="head" :value="WORKTREE_REF">
                        <span class="option__text">
                            <span class="option__label">Local state</span>
                            <span class="option__meta">{{ worktreeMeta }}</span>
                        </span>
                    </label>
                    <label class="option option--custom">
                        <input v-model="headChoice" type="radio" name="head" :value="CUSTOM" aria-label="Other ref">
                        <span class="option__inline">Other ref</span>
                        <input v-model="headCustom" type="text" class="text-input mono" list="explorer-refs" placeholder="for example v6.7.0.0" aria-label="Other compare ref" @focus="headChoice = CUSTOM">
                    </label>
                </fieldset>

                <datalist id="explorer-refs">
                    <option v-for="entry in suggestions" :key="entry.kind + entry.name" :value="entry.name" />
                </datalist>

                <p class="muted small">The first read of a ref can take 10–30 s. Later reads come from the cache. A pull request takes about a minute the first time.</p>

                <div class="dialog__actions">
                    <button type="button" class="button" @click="$emit('cancel')">Cancel</button>
                    <button type="submit" class="button button--primary" :disabled="!canApply">Apply</button>
                </div>
            </form>
        </div>
    `,
});
