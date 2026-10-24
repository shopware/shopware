/**
 * @sw-package framework
 */

import type { CompareResponse, ErrorResponse, PullRequestResponse, StateResponse } from '../server/api-types';

export type { CompareResponse, PullRequestResponse, StateResponse };

async function getJson<T>(url: string): Promise<T> {
    let response: Response;

    try {
        response = await fetch(url, { headers: { Accept: 'application/json' } });
    } catch {
        throw new Error('The explorer server cannot be reached. Check that it is still running.');
    }

    let body: unknown = null;

    try {
        body = await response.json();
    } catch {
        body = null;
    }

    if (!response.ok) {
        const message = (body as Partial<ErrorResponse> | null)?.error;
        throw new Error(message ?? `Request failed with status ${response.status}.`);
    }

    return body as T;
}

export function fetchState(): Promise<StateResponse> {
    return getJson<StateResponse>('/api/state');
}

export function fetchCompare(base: string, head: string, refresh = false): Promise<CompareResponse> {
    const params = new URLSearchParams({ base, head });

    if (refresh) {
        params.set('refresh', '1');
    }

    return getJson<CompareResponse>(`/api/compare?${params.toString()}`);
}

export function fetchPullRequest(input: string, refresh = false): Promise<PullRequestResponse> {
    const params = new URLSearchParams({ pr: input });

    if (refresh) {
        params.set('refresh', '1');
    }

    return getJson<PullRequestResponse>(`/api/pull-request?${params.toString()}`);
}
