/**
 * Trunk's Codecov report is normally already `complete` by the time this runs — every PR
 * merge uploads coverage on its own, independently of this schedule. The poll is only a
 * safety net for the rare case where the run lands right as the latest merge is still
 * aggregating.
 */

export interface CodecovCommitTotals {
    coverage: number;
    lines: number;
    hits: number;
}

export interface CodecovCommitResponse {
    state: 'complete' | 'pending' | 'error' | 'skipped';
    totals: CodecovCommitTotals | null;
}

export const REQUEST_TIMEOUT_MS = 30_000;
export const REQUEST_RETRY_DELAYS_MS = [2_000, 4_000, 8_000];

export class HttpError extends Error {
    readonly status: number;

    constructor(message: string, status: number) {
        super(message);
        this.status = status;
    }
}

export function isRetryable(error: unknown): boolean {
    if (error instanceof HttpError) {
        return error.status === 429 || error.status >= 500;
    }

    // Node's fetch rejects with a TypeError ("fetch failed") on network errors, AbortSignal.timeout with a TimeoutError.
    return error instanceof TypeError || (error instanceof Error && error.name === 'TimeoutError');
}

/**
 * Node's fetch hides the actual reason (ECONNRESET, ENOTFOUND, ...) in `cause`, so `message` alone is just "fetch failed".
 */
export function describeError(error: unknown): string {
    const parts: string[] = [];
    let current: unknown = error;

    while (current !== undefined && current !== null) {
        if (!(current instanceof Error)) {
            parts.push(String(current));
            break;
        }

        const code = 'code' in current && current.code ? ` [${String(current.code)}]` : '';
        if (current.message || code) {
            parts.push(`${current.message}${code}`.trim());
        }

        if (current instanceof AggregateError) {
            parts.push(...current.errors.map((inner: unknown) => describeError(inner)));
        }
        current = current.cause;
    }

    return parts.join(' <- caused by: ');
}

export async function withRetry<T>(
    label: string,
    operation: () => Promise<T>,
    sleep: (ms: number) => Promise<void>,
    delaysMs: readonly number[] = REQUEST_RETRY_DELAYS_MS,
    warn: (message: string) => void = console.warn,
): Promise<T> {
    for (let attempt = 0; ; attempt++) {
        try {
            return await operation();
        } catch (error) {
            const delay = delaysMs[attempt];
            if (delay === undefined || !isRetryable(error)) {
                throw new Error(`${label} failed after ${attempt + 1} attempt(s)`, { cause: error });
            }
            warn(
                `${label} failed (attempt ${attempt + 1}/${delaysMs.length + 1}): ${describeError(error)}; ` +
                    `retrying in ${delay / 1_000}s`,
            );
            await sleep(delay);
        }
    }
}

export function codecovCommitUrl(owner: string, repo: string, sha: string): string {
    return `https://api.codecov.io/api/v2/github/${owner}/repos/${repo}/commits/${sha}/`;
}

export async function fetchCodecovCommit(
    owner: string,
    repo: string,
    sha: string,
    token: string,
    fetchImpl: typeof fetch = fetch,
): Promise<CodecovCommitResponse> {
    const response = await fetchImpl(codecovCommitUrl(owner, repo, sha), {
        headers: { Authorization: `Bearer ${token}` },
        signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS),
    });

    if (!response.ok) {
        throw new HttpError(
            `Codecov API returned ${response.status} for commit ${sha}: ${await response.text()}`,
            response.status,
        );
    }

    return (await response.json()) as CodecovCommitResponse;
}

export const CODECOV_POLL_DELAYS_MS = [10_000, 20_000, 30_000];

export class CodecovReportNotReadyError extends Error {}

export async function waitForCodecovReport(
    fetchCommit: () => Promise<CodecovCommitResponse>,
    sleep: (ms: number) => Promise<void>,
    delaysMs: readonly number[] = CODECOV_POLL_DELAYS_MS,
    warn: (message: string) => void = console.warn,
): Promise<CodecovCommitResponse> {
    for (let attempt = 0; attempt <= delaysMs.length; attempt++) {
        const commit = await fetchCommit();

        if (commit.state === 'complete') {
            return commit;
        }
        if (commit.state === 'error') {
            throw new Error('Codecov report failed to process (state: error)');
        }

        const delay = delaysMs[attempt];
        if (delay === undefined) {
            break;
        }
        warn(`Codecov report is still "${commit.state}"; retrying in ${delay / 1_000}s`);
        await sleep(delay);
    }

    throw new CodecovReportNotReadyError(
        `Codecov report did not reach "complete" after ${delaysMs.length + 1} attempts`,
    );
}

export interface GetDxCoveragePayload {
    reference: string;
    key: 'code_coverage';
    timestamp: string;
    value: {
        repo: string;
        branch: string;
        coverage_pct: number;
        lines_covered: number;
        lines_total: number;
    };
}

export function buildGetDxPayload(
    sha: string,
    repo: string,
    branch: string,
    totals: CodecovCommitTotals | null,
    timestamp: string,
): GetDxCoveragePayload {
    if (totals === null) {
        throw new Error(`Codecov commit ${sha} has no totals even though its report is complete`);
    }

    return {
        reference: sha,
        key: 'code_coverage',
        timestamp,
        value: {
            repo,
            branch,
            coverage_pct: Math.round(totals.coverage * 100) / 100,
            lines_covered: totals.hits,
            lines_total: totals.lines,
        },
    };
}

export async function pushCoverageToGetDx(
    payload: GetDxCoveragePayload,
    token: string,
    fetchImpl: typeof fetch = fetch,
): Promise<void> {
    const response = await fetchImpl('https://shopware.getdx.net/api/customData.set', {
        method: 'POST',
        headers: {
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json',
            Accept: 'application/json',
        },
        body: JSON.stringify(payload),
        signal: AbortSignal.timeout(REQUEST_TIMEOUT_MS),
    });

    if (!response.ok) {
        throw new HttpError(
            `getDX customData.set POST failed with ${response.status}: ${await response.text()}`,
            response.status,
        );
    }
}

function requireEnv(name: string): string {
    const value = process.env[name];
    if (!value) {
        throw new Error(`missing required environment variable ${name}`);
    }

    return value;
}

async function run(): Promise<void> {
    const sha = requireEnv('GITHUB_SHA');
    const branch = requireEnv('GITHUB_REF_NAME');
    const [owner, repo] = requireEnv('GITHUB_REPOSITORY').split('/');
    const codecovToken = requireEnv('CODECOV_API_TOKEN');
    const dxToken = requireEnv('DX_API_TOKEN');

    const sleep = (ms: number) => new Promise<void>((resolve) => setTimeout(resolve, ms));

    console.log(`Fetching Codecov report for ${owner}/${repo}@${sha}`);
    const commit = await waitForCodecovReport(
        () => withRetry('Codecov commit request', () => fetchCodecovCommit(owner, repo, sha, codecovToken), sleep),
        sleep,
    );

    const payload = buildGetDxPayload(sha, repo, branch, commit.totals, new Date().toISOString());

    console.log('Pushing coverage to getDX');
    await withRetry('getDX customData.set request', () => pushCoverageToGetDx(payload, dxToken), sleep);

    console.log(
        `Pushed coverage for ${sha} to getDX: ${payload.value.coverage_pct}% ` +
            `(${payload.value.lines_covered}/${payload.value.lines_total} lines)`,
    );
}

if (import.meta.url === `file://${process.argv[1]}`) {
    run().catch((error: unknown) => {
        console.error(`::error title=Push coverage to getDX::${describeError(error)}`);
        process.exit(1);
    });
}
