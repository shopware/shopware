/**
 * @sw-package framework
 */

import type { PullRequestResponse } from '../../server/api-types';

export interface PullRequestRefLabel {
    side: 'head' | 'base';
    /** What follows `pr:` or `pr-base:`, as the server's `pr` query parameter accepts it. */
    input: string;
    number: number | null;
}

/** The number as written in `19666`, `#19666`, `owner/repo/19666` or a GitHub pull request URL. */
export function pullRequestNumber(input: string): number | null {
    const trimmed = input.trim();
    const match = /\/pull\/(\d+)/.exec(trimmed) ?? /(?:^|[/#])(\d+)$/.exec(trimmed);

    return match ? Number(match[1]) : null;
}

export function parsePullRequestRefLabel(ref: string): PullRequestRefLabel | null {
    const match = /^(pr|pr-base):(.+)$/s.exec(ref);

    if (!match) {
        return null;
    }

    return { side: match[1] === 'pr' ? 'head' : 'base', input: match[2], number: pullRequestNumber(match[2]) };
}

/** The pull request a comparison is about; the head wins when both sides name one. */
export function comparedPullRequest(base: string, head: string): PullRequestRefLabel | null {
    return parsePullRequestRefLabel(head) ?? parsePullRequestRefLabel(base);
}

export function pullRequestTitle(number: number | null, title: string | null | undefined): string {
    const name = number === null ? 'PR' : `PR #${number}`;

    return title ? `${name} · ${title}` : name;
}

export function pullRequestState(pullRequest: PullRequestResponse): string {
    if (pullRequest.merged) {
        return 'merged';
    }

    return pullRequest.state ?? 'state unknown';
}

/** Matches the loaded pull request only when it is the one the ref names. */
function matching(label: PullRequestRefLabel, pullRequest: PullRequestResponse | null): PullRequestResponse | null {
    return pullRequest && pullRequest.number === label.number ? pullRequest : null;
}

export function pullRequestSideName(label: PullRequestRefLabel, pullRequest: PullRequestResponse | null): string {
    const loaded = matching(label, pullRequest);

    if (label.side === 'base') {
        return loaded ? `merge base with ${loaded.baseRef}` : 'merge base';
    }

    return 'PR head';
}

export function pullRequestUrl(label: PullRequestRefLabel, pullRequest: PullRequestResponse | null): string | null {
    return matching(label, pullRequest)?.url ?? null;
}

/** Plain words for the errors of `/api/pull-request` and of `fetch` itself. */
export function describePullRequestError(message: string): { text: string; detail: string | null } {
    const unknown = /^Unknown pull request #(\d+) of (\S+)$/.exec(message);

    if (unknown) {
        return { text: `There is no pull request #${unknown[1]} in ${unknown[2]}.`, detail: null };
    }

    if (message.startsWith('Invalid pull request') || message.startsWith('Invalid GitHub repository')) {
        return {
            text: 'Enter a pull request number such as 19666 or #19666, or paste its GitHub link.',
            detail: null,
        };
    }

    const fetchFailed = /^Fetching pull request #(\d+) of (\S+) failed: (.*)$/s.exec(message);

    if (fetchFailed) {
        return {
            text: `PR #${fetchFailed[1]} could not be downloaded from GitHub. Check the network connection and try again.`,
            detail: fetchFailed[3],
        };
    }

    return { text: message, detail: null };
}
