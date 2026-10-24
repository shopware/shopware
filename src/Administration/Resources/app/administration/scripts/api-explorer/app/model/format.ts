/**
 * @sw-package framework
 */

import { WORKTREE_REF } from '../../snapshot/types';
import { parsePullRequestRefLabel } from './pull-request';

export { WORKTREE_REF };

export function shortSha(commit: string | null | undefined): string {
    return commit ? commit.slice(0, 7) : '';
}

export function formatCount(value: number): string {
    return value.toLocaleString('en-US');
}

export function refLabel(ref: string): string {
    const pullRequest = parsePullRequestRefLabel(ref);

    if (pullRequest) {
        const name = pullRequest.number === null ? 'PR' : `PR #${pullRequest.number}`;

        return pullRequest.side === 'head' ? `${name} head` : `${name} merge base`;
    }

    return ref === WORKTREE_REF ? 'Local state' : ref;
}

/** Short side name for "Before (…)" / "After (…)" headings. */
export function sideLabel(ref: string): string {
    const pullRequest = parsePullRequestRefLabel(ref);

    if (pullRequest) {
        return pullRequest.side === 'head' ? 'PR head' : 'merge base';
    }

    return ref === WORKTREE_REF ? 'local' : ref;
}

export function formatSeconds(milliseconds: number): string {
    const seconds = milliseconds / 1000;

    return `${seconds < 10 ? seconds.toFixed(1) : Math.round(seconds).toString()} s`;
}

export function formatAge(milliseconds: number): string {
    const seconds = Math.max(0, Math.floor(milliseconds / 1000));

    if (seconds < 5) {
        return 'just now';
    }

    if (seconds < 60) {
        return `${seconds} s ago`;
    }

    const minutes = Math.floor(seconds / 60);

    return minutes < 60 ? `${minutes} min ago` : `${Math.floor(minutes / 60)} h ago`;
}

export function matchesQuery(text: string, query: string): boolean {
    const needle = query.trim().toLowerCase();

    return needle === '' || text.toLowerCase().includes(needle);
}
