/**
 * @sw-package framework
 */

import * as http from 'node:http';
import type { AddressInfo } from 'node:net';
import type { ApiDiff } from '../diff/types';
import {
    assertSafeRef,
    getDefaultPullRequestResolver,
    GitRefError,
    parsePullRequestInput,
    type PullRequestInput,
    type PullRequestResolution,
} from '../git';
import type { SnapshotService } from '../service/snapshot-service';
import { WORKTREE_REF, type ApiSnapshot } from '../snapshot/types';
import type { CompareResponse, ErrorResponse, PullRequestResponse, StateResponse } from './api-types';

export const DEFAULT_BASE_REF = 'trunk';

export type ExplorerSnapshotService = Pick<SnapshotService, 'getSnapshot' | 'listRefs' | 'status'>;

export type ResolvePullRequest = (input: PullRequestInput, options: { refresh: boolean }) => Promise<PullRequestResolution>;

export type Middleware = (request: http.IncomingMessage, response: http.ServerResponse, next: () => void) => void;

export interface UiHandler {
    middleware: Middleware;
    close: () => Promise<void>;
}

/** Called once the HTTP server exists, so a dev server can attach its websocket to it. */
export type UiFactory = (httpServer: http.Server) => Promise<UiHandler>;

export interface ExplorerServerOptions {
    service: ExplorerSnapshotService;
    createDiff: (base: ApiSnapshot, head: ApiSnapshot) => ApiDiff;
    port: number;
    host: string;
    /** Serves everything outside `/api/`; without it those paths answer 404. */
    ui?: UiFactory;
    /** Defaults to the shared resolver, which also serves `pr:` refs of the snapshot service. */
    resolvePullRequest?: ResolvePullRequest;
}

export interface ExplorerServer {
    /** The bound port, useful when `port` was 0. */
    port: number;
    url: string;
    close: () => Promise<void>;
}

class BadRequestError extends Error {}

function sendJson(response: http.ServerResponse, statusCode: number, body: unknown): void {
    const json = JSON.stringify(body);

    response.writeHead(statusCode, {
        'Content-Type': 'application/json; charset=utf-8',
        'Content-Length': Buffer.byteLength(json),
        'Cache-Control': 'no-store',
    });
    response.end(json);
}

function sendError(response: http.ServerResponse, statusCode: number, message: string): void {
    sendJson(response, statusCode, { error: message } satisfies ErrorResponse);
}

function readRef(params: URLSearchParams, name: string, fallback: string): string {
    const value = (params.get(name) ?? fallback).trim();

    if (value === '') {
        throw new BadRequestError(`Query parameter "${name}" must not be empty.`);
    }

    if (value !== WORKTREE_REF) {
        assertSafeRef(value);
    }

    return value;
}

const LOOPBACK_HOSTS = new Set([
    'localhost',
    '127.0.0.1',
    '[::1]',
]);
const WILDCARD_HOSTS = new Set([
    '0.0.0.0',
    '::',
    '',
]);

function hostnameOf(host: string): string | null {
    try {
        return new URL(`http://${host}`).hostname.toLowerCase();
    } catch {
        return null;
    }
}

/**
 * The API fetches pull requests and starts extractions, so only the explorer's own pages may call it:
 * a foreign `Host` means DNS rebinding, a foreign `Origin` or `Sec-Fetch-Site` another web page.
 */
function isTrustedApiRequest(request: http.IncomingMessage, allowedHosts: Set<string>): boolean {
    const host = hostnameOf(request.headers.host ?? '');

    if (host === null || !allowedHosts.has(host)) {
        return false;
    }

    const fetchSite = request.headers['sec-fetch-site'];

    if (fetchSite !== undefined && fetchSite !== 'same-origin' && fetchSite !== 'none') {
        return false;
    }

    const origin = request.headers.origin;

    if (origin === undefined) {
        return true;
    }

    try {
        const url = new URL(origin);

        return (url.protocol === 'http:' || url.protocol === 'https:') && url.host === request.headers.host?.toLowerCase();
    } catch {
        return false;
    }
}

async function timed<T>(task: () => Promise<T> | T): Promise<[T, number]> {
    const start = performance.now();
    const result = await task();

    return [
        result,
        Math.round(performance.now() - start),
    ];
}

export async function createExplorerServer({
    service,
    createDiff,
    port,
    host,
    ui,
    resolvePullRequest: resolvePullRequestOption,
}: ExplorerServerOptions): Promise<ExplorerServer> {
    let repoRoot: Promise<string> | null = null;

    const resolvePullRequest: ResolvePullRequest =
        resolvePullRequestOption ??
        (async (input, options) => {
            repoRoot ??= service.status().then((status) => status.repoRoot);

            return getDefaultPullRequestResolver().resolve(await repoRoot, input, options);
        });

    async function handleState(): Promise<StateResponse> {
        const [
            status,
            refs,
        ] = await Promise.all([
            service.status(),
            service.listRefs(),
        ]);

        return { status, refs, defaults: { base: DEFAULT_BASE_REF, head: WORKTREE_REF } };
    }

    async function handleCompare(params: URLSearchParams): Promise<CompareResponse> {
        const baseRef = readRef(params, 'base', DEFAULT_BASE_REF);
        const headRef = readRef(params, 'head', WORKTREE_REF);
        const refresh = params.get('refresh') === '1';

        // One after the other: two extractions at once would double the memory peak.
        const [
            base,
            baseMs,
        ] = await timed(() => service.getSnapshot(baseRef, { refresh: refresh && baseRef === WORKTREE_REF }));
        const [
            head,
            headMs,
        ] = await timed(() => service.getSnapshot(headRef, { refresh: refresh && headRef === WORKTREE_REF }));
        const [
            diff,
            diffMs,
        ] = await timed(() => createDiff(base, head));

        return { base, head, diff, timings: { baseMs, headMs, diffMs } };
    }

    async function handlePullRequest(params: URLSearchParams): Promise<PullRequestResponse> {
        const value = params.get('pr')?.trim() ?? '';

        if (value === '') {
            throw new BadRequestError('Query parameter "pr" must not be empty.');
        }

        const input = parsePullRequestInput(value);
        const { number, owner, repo, title, state, merged, url, baseRef, headSha, mergeBaseSha, warnings } =
            await resolvePullRequest(input, { refresh: params.get('refresh') === '1' });
        const label = input.owner ? `${owner}/${repo}/${number}` : String(number);

        return {
            number,
            owner,
            repo,
            title,
            state,
            merged,
            url,
            baseRef,
            headSha,
            mergeBaseSha,
            warnings,
            refs: { base: `pr-base:${label}`, head: `pr:${label}` },
        };
    }

    const routes: Record<string, (params: URLSearchParams) => Promise<unknown>> = {
        '/api/state': handleState,
        '/api/compare': handleCompare,
        '/api/pull-request': handlePullRequest,
    };

    const configuredHost = hostnameOf(host.includes(':') && !host.startsWith('[') ? `[${host}]` : host);
    const allowedHosts = new Set(LOOPBACK_HOSTS);

    if (configuredHost !== null && !WILDCARD_HOSTS.has(configuredHost) && configuredHost !== '[::]') {
        allowedHosts.add(configuredHost);
    }

    async function handleApi(request: http.IncomingMessage, response: http.ServerResponse, url: URL): Promise<void> {
        if (!isTrustedApiRequest(request, allowedHosts)) {
            sendError(response, 403, 'The explorer API only answers its own pages on this machine.');
            return;
        }

        const route = Object.hasOwn(routes, url.pathname) ? routes[url.pathname] : undefined;

        if (!route) {
            sendError(response, 404, `Unknown endpoint: ${url.pathname}`);
            return;
        }

        if (request.method !== 'GET') {
            response.setHeader('Allow', 'GET');
            sendError(response, 405, `Method ${request.method ?? ''} is not allowed.`);
            return;
        }

        try {
            sendJson(response, 200, await route(url.searchParams));
        } catch (error) {
            const message = error instanceof Error ? error.message : String(error);

            if (error instanceof BadRequestError || error instanceof GitRefError) {
                sendError(response, 400, message);
                return;
            }

            console.error(error);
            sendError(response, 500, message);
        }
    }

    let uiHandler: UiHandler | null = null;

    const httpServer = http.createServer((request, response) => {
        const url = new URL(request.url ?? '/', 'http://localhost');

        if (url.pathname === '/api' || url.pathname.startsWith('/api/')) {
            void handleApi(request, response, url);
            return;
        }

        if (!uiHandler) {
            sendError(response, 404, 'Not found');
            return;
        }

        uiHandler.middleware(request, response, () => sendError(response, 404, 'Not found'));
    });

    uiHandler = ui ? await ui(httpServer) : null;

    await new Promise<void>((resolve, reject) => {
        httpServer.once('error', reject);
        httpServer.listen(port, host, () => {
            httpServer.off('error', reject);
            resolve();
        });
    });

    const boundPort = (httpServer.address() as AddressInfo).port;
    const displayHost = host === '0.0.0.0' || host === '::' ? 'localhost' : host;

    return {
        port: boundPort,
        url: `http://${displayHost.includes(':') ? `[${displayHost}]` : displayHost}:${boundPort}/`,
        async close() {
            await uiHandler?.close();
            httpServer.closeAllConnections();
            await new Promise<void>((resolve, reject) => {
                httpServer.close((error) => (error ? reject(error) : resolve()));
            });
        },
    };
}
