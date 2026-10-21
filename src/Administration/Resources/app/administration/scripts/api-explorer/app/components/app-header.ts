/**
 * @sw-package framework
 */

import { computed, defineComponent, onBeforeUnmount, ref, type PropType } from 'vue';
import type { PullRequestResponse } from '../api';
import { formatAge, formatSeconds, refLabel, shortSha, WORKTREE_REF } from '../model/format';
import {
    comparedPullRequest,
    parsePullRequestRefLabel,
    pullRequestSideName,
    pullRequestTitle,
    pullRequestUrl,
} from '../model/pull-request';

export interface SideInfo {
    ref: string;
    commit: string | null;
}

export const AppHeader = defineComponent({
    name: 'AppHeader',
    props: {
        base: { type: Object as PropType<SideInfo>, required: true },
        head: { type: Object as PropType<SideInfo>, required: true },
        uncommittedFiles: { type: Number as PropType<number | null>, default: null },
        loading: { type: Boolean, default: false },
        /** Epoch milliseconds of the last successful read. */
        loadedAt: { type: Number as PropType<number | null>, default: null },
        durationMs: { type: Number as PropType<number | null>, default: null },
        query: { type: String, default: '' },
        /** ISO 8601; set in a static report, which has fixed refs and cannot refresh. */
        generatedAt: { type: String as PropType<string | null>, default: null },
        pullRequest: { type: Object as PropType<PullRequestResponse | null>, default: null },
    },
    emits: [
        'open-compare',
        'swap',
        'refresh',
        'update:query',
    ],
    setup(props) {
        const now = ref(Date.now());
        const timer = window.setInterval(() => {
            now.value = Date.now();
        }, 1000);
        onBeforeUnmount(() => window.clearInterval(timer));

        const info = computed(() => {
            if (props.generatedAt !== null) {
                return `Static report, generated ${new Date(props.generatedAt).toLocaleString('en-GB', { dateStyle: 'medium', timeStyle: 'short' })}`;
            }

            if (props.loading) {
                return props.head.ref === WORKTREE_REF ? 'reading local state …' : 'reading …';
            }

            if (props.loadedAt === null) {
                return '';
            }

            const duration = props.durationMs !== null ? ` in ${formatSeconds(props.durationMs)}` : '';

            return `read ${formatAge(now.value - props.loadedAt)}${duration}`;
        });

        const side = (sideInfo: SideInfo, fallbackName: string) => {
            const label = parsePullRequestRefLabel(sideInfo.ref);

            if (!label) {
                return { name: fallbackName, sha: shortSha(sideInfo.commit), isPullRequest: false };
            }

            const loaded = props.pullRequest?.number === label.number ? props.pullRequest : null;
            const loadedSha = label.side === 'head' ? loaded?.headSha : loaded?.mergeBaseSha;

            return {
                name: pullRequestSideName(label, props.pullRequest),
                sha: shortSha(sideInfo.commit ?? loadedSha),
                isPullRequest: true,
            };
        };
        const baseSide = computed(() => side(props.base, refLabel(props.base.ref)));
        const headSide = computed(() =>
            side(
                props.head,
                props.generatedAt !== null && props.head.ref === WORKTREE_REF
                    ? 'Checked-out commit'
                    : refLabel(props.head.ref),
            ),
        );
        const pullRequestLink = computed(() => {
            const label = comparedPullRequest(props.base.ref, props.head.ref);

            if (!label) {
                return null;
            }

            const loaded = props.pullRequest?.number === label.number ? props.pullRequest : null;

            return { text: pullRequestTitle(label.number, loaded?.title), url: pullRequestUrl(label, props.pullRequest) };
        });

        return {
            info,
            pullRequestLink,
            baseName: computed(() => baseSide.value.name),
            baseSha: computed(() =>
                baseSide.value.isPullRequest && baseSide.value.sha ? `@ ${baseSide.value.sha}` : baseSide.value.sha,
            ),
            headName: computed(() => headSide.value.name),
            headSha: computed(() =>
                headSide.value.isPullRequest && headSide.value.sha ? `@ ${headSide.value.sha}` : headSide.value.sha,
            ),
            headIsWorktree: computed(() => props.head.ref === WORKTREE_REF),
            isStatic: computed(() => props.generatedAt !== null),
        };
    },
    template: `
        <header class="topbar">
            <a href="#/" class="brand">
                <span class="brand__logo" aria-hidden="true">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 4H6a2 2 0 0 0-2 2v4l-2 2 2 2v4a2 2 0 0 0 2 2h2"/><path d="M16 4h2a2 2 0 0 1 2 2v4l2 2-2 2v4a2 2 0 0 1-2 2h-2"/><path d="M9 12h6"/><path d="M12 9v6"/></svg>
                </span>
                <span class="brand__name">Admin API Explorer</span>
            </a>

            <div v-if="isStatic" class="refs">
                <div class="ref-chip ref-chip--static">
                    <span class="ref-chip__label">Base</span>
                    <span class="ref-chip__value">{{ baseName }} <span v-if="baseSha" class="ref-chip__sha">{{ baseSha }}</span></span>
                </div>
                <span class="refs__arrow" aria-hidden="true">→</span>
                <div class="ref-chip ref-chip--static">
                    <span class="ref-chip__label">Compare</span>
                    <span class="ref-chip__value">{{ headName }} <span v-if="headSha" class="ref-chip__sha">{{ headIsWorktree ? '@ ' : '' }}{{ headSha }}</span></span>
                </div>
                <template v-if="pullRequestLink">
                    <a v-if="pullRequestLink.url" class="pr-link" :href="pullRequestLink.url" target="_blank" rel="noopener noreferrer" data-testid="pull-request-link">{{ pullRequestLink.text }}</a>
                    <span v-else class="pr-link" data-testid="pull-request-link">{{ pullRequestLink.text }}</span>
                </template>
            </div>
            <div v-else class="refs">
                <button type="button" class="ref-chip" @click="$emit('open-compare')">
                    <span class="ref-chip__label">Base</span>
                    <span class="ref-chip__value">{{ baseName }} <span v-if="baseSha" class="ref-chip__sha">{{ baseSha }}</span></span>
                </button>
                <button type="button" class="icon-button" aria-label="Swap base and comparison" title="Swap base and comparison" :disabled="loading" @click="$emit('swap')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 3 4 7l4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/></svg>
                </button>
                <button type="button" class="ref-chip" @click="$emit('open-compare')">
                    <span class="ref-chip__label">Compare</span>
                    <span class="ref-chip__value">{{ headName }}
                        <span v-if="headIsWorktree && uncommittedFiles !== null" class="ref-chip__dirty">· {{ uncommittedFiles }} uncommitted</span>
                        <span v-else-if="headSha" class="ref-chip__sha">{{ headSha }}</span>
                    </span>
                </button>
                <template v-if="pullRequestLink">
                    <a v-if="pullRequestLink.url" class="pr-link" :href="pullRequestLink.url" target="_blank" rel="noopener noreferrer" data-testid="pull-request-link">{{ pullRequestLink.text }}</a>
                    <span v-else class="pr-link" data-testid="pull-request-link">{{ pullRequestLink.text }}</span>
                </template>
            </div>

            <div class="topbar__actions">
                <button v-if="!isStatic" type="button" class="button" :disabled="loading" :aria-busy="loading ? 'true' : 'false'" @click="$emit('refresh')">
                    <svg :class="{ spin: loading }" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 16h5v5"/></svg>
                    {{ loading ? 'Reading …' : 'Refresh' }}
                </button>
                <span class="topbar__info" role="status">{{ info }}</span>
                <label class="search">
                    <span class="visually-hidden">Search the API</span>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                    <input type="search" placeholder="Search by name …" :value="query" @input="$emit('update:query', $event.target.value)">
                </label>
            </div>
        </header>
    `,
});
