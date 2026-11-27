/**
 * @sw-package framework
 *
 * The states every direction shows, chosen by the address hash so the overview can link each one. The data is
 * static: nothing here calls GitHub or runs the check.
 */
import { computed, onBeforeUnmount, ref, shallowRef, watch } from 'vue';
import type { Ref } from 'vue';
import type { Report } from './report';

/**
 * @private
 *
 * A pull request, or two revisions: branches, tags or hashes.
 */
export type CompareRequest = { pullRequest: number } | { base: string; head: string };

/** @private */
export type StateName = 'start' | 'loading' | 'small' | 'large' | 'error-pr' | 'error-limit';

/** @private */
export type ViewState =
    | { kind: 'start' }
    | { kind: 'loading'; request: CompareRequest }
    | { kind: 'error'; request: CompareRequest; reason: 'unknown' | 'limit'; message: string }
    | { kind: 'report'; request: CompareRequest; report: Report; entry: 'link' | 'typed' };

/** @private */
export const STATES: ReadonlyArray<{ name: StateName; label: string }> = [
    { name: 'small', label: 'Small report' },
    { name: 'large', label: 'Large report' },
    { name: 'start', label: 'Start' },
    { name: 'loading', label: 'Loading' },
    { name: 'error-pr', label: 'Error: unknown pull request' },
    { name: 'error-limit', label: 'Error: request limit' },
];

const SMALL: CompareRequest = { pullRequest: 20959 };
const LARGE: CompareRequest = { base: 'v6.7.0.0', head: 'trunk' };

/**
 * @private
 *
 * The generator commit of the page, as the build of the prototype writes it into the footer.
 */
export const GENERATOR = 'a7ee19cfd995';

/** GitHub stops at 60 requests per hour without a login, and names the time it resets. */
const LIMIT_RESET = '14:32';

/** Each state, built from what a person typed on the start screen, or from the example when they typed nothing. */
const STATE_OF: Record<StateName, (typed: CompareRequest | null) => ViewState> = {
    start: () => ({ kind: 'start' }),
    loading: (typed) => ({ kind: 'loading', request: typed ?? SMALL }),
    small: (typed) => ({ kind: 'report', request: SMALL, report: window.REPORTS.small, entry: typed ? 'typed' : 'link' }),
    large: (typed) => ({ kind: 'report', request: typed ?? LARGE, report: window.REPORTS.large, entry: 'typed' }),
    'error-pr': (typed) => {
        const request = typed !== null && 'pullRequest' in typed ? typed : { pullRequest: 29959 };

        return {
            kind: 'error',
            request,
            reason: 'unknown',
            message: `GitHub does not know pull request #${request.pullRequest}.`,
        };
    },
    'error-limit': (typed) => ({
        kind: 'error',
        request: typed ?? LARGE,
        reason: 'limit',
        message: `GitHub allows 60 requests per hour without a login, and they are used up until ${LIMIT_RESET}.`,
    }),
};

function nameFromHash(): StateName {
    const hash = window.location.hash.slice(1);

    return STATES.some(({ name }) => name === hash) ? (hash as StateName) : 'small';
}

/** What a typed comparison leads to: #20959 has a report, other numbers are unknown, revisions give the large one. */
function outcomeOf(request: CompareRequest): StateName {
    if ('pullRequest' in request) {
        return request.pullRequest === 20959 ? 'small' : 'error-pr';
    }

    return 'large';
}

/** @private */
export interface Progress {
    step: 'commits' | 'files' | 'comparing';
    loaded: number;
    total: number;
    message: string;
}

const FILES = 2719;

/** Plays the progress of a comparison. The state from the review bar loops, a typed comparison ends in its outcome. */
function useProgress(state: Ref<ViewState>, done: () => void, looping: Ref<boolean>): Ref<Progress> {
    const progress = ref<Progress>({ step: 'commits', loaded: 0, total: FILES, message: 'Asking GitHub for the commits.' });
    let timer: ReturnType<typeof setInterval> | undefined;

    const stop = (): void => {
        if (timer !== undefined) {
            clearInterval(timer);
            timer = undefined;
        }
    };

    watch(
        () => state.value.kind,
        (kind) => {
            stop();

            if (kind !== 'loading') {
                return;
            }

            let tick = 0;

            timer = setInterval(() => {
                tick += 1;

                const loaded = Math.min(FILES, Math.max(0, Math.round(((tick - 6) / 40) * FILES)));

                if (tick < 6) {
                    progress.value = { step: 'commits', loaded: 0, total: FILES, message: 'Asking GitHub for the commits.' };
                } else if (loaded < FILES) {
                    progress.value = {
                        step: 'files',
                        loaded,
                        total: FILES,
                        message: `Loading the sources, ${loaded.toLocaleString('en')} of ${FILES.toLocaleString('en')} files.`,
                    };
                } else if (tick < 56) {
                    progress.value = { step: 'comparing', loaded: FILES, total: FILES, message: 'Comparing.' };
                } else if (looping.value) {
                    tick = 0;
                } else {
                    stop();
                    done();
                }
            }, 100);
        },
        { immediate: true },
    );

    onBeforeUnmount(stop);

    return progress;
}

/**
 * @private
 *
 * The state a direction renders, and what its controls can do with it.
 */
export function useDesignState() {
    const name = ref<StateName>(nameFromHash());
    const typed = shallowRef<CompareRequest | null>(null);
    const looping = ref(true);
    const target = ref<StateName>('small');
    const state = computed(() => STATE_OF[name.value](typed.value));

    const show = (next: StateName): void => {
        typed.value = null;
        looping.value = true;
        name.value = next;
        window.history.replaceState(null, '', `#${next}`);
    };

    const progress = useProgress(
        state,
        () => {
            name.value = target.value;
            window.history.replaceState(null, '', `#${target.value}`);
        },
        looping,
    );

    const compare = (request: CompareRequest): void => {
        typed.value = request;
        target.value = outcomeOf(request);
        looping.value = false;
        name.value = 'loading';
    };

    function onHashChange(): void {
        show(nameFromHash());
    }

    window.addEventListener('hashchange', onHashChange);
    onBeforeUnmount(() => window.removeEventListener('hashchange', onHashChange));

    return { name, state, progress, show, compare };
}

/**
 * @private
 *
 * Reads what a person typed into one field: `20959`, `#20959`, a pull request link, or `base...head`.
 */
export function requestFromText(text: string): CompareRequest | null {
    const value = text.trim();
    const pullRequest = /^(?:#|.*\/pull\/)?(\d+)\/?$/.exec(value);

    if (pullRequest !== null) {
        return { pullRequest: Number(pullRequest[1]) };
    }

    const revisions = /^(\S+?)\s*(?:\.{2,3}|\s)\s*(\S+)$/.exec(value);

    return revisions === null ? null : { base: revisions[1], head: revisions[2] };
}

/** @private */
export function requestText(request: CompareRequest): string {
    return 'pullRequest' in request ? `#${request.pullRequest}` : `${request.base}...${request.head}`;
}
