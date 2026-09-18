/**
 * Codecov keeps aggregating a commit's flags for a while after the last shard's upload lands,
 * so its `state` is polled until `complete` before reading totals and pushing them to getDX's
 * custom_data endpoint, keyed by commit SHA.
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
    });

    if (!response.ok) {
        throw new Error(`Codecov API returned ${response.status} for commit ${sha}`);
    }

    return (await response.json()) as CodecovCommitResponse;
}

export const CODECOV_POLL_DELAYS_MS = [30_000, 30_000, 30_000, 60_000, 60_000, 60_000, 60_000, 60_000];

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
    const response = await fetchImpl('https://app.getdx.com/api/custom_data', {
        method: 'POST',
        headers: {
            Authorization: `Bearer ${token}`,
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload),
    });

    if (!response.ok) {
        throw new Error(`getDX custom_data POST failed with ${response.status}: ${await response.text()}`);
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

    const commit = await waitForCodecovReport(
        () => fetchCodecovCommit(owner, repo, sha, codecovToken),
        (ms) => new Promise((resolve) => setTimeout(resolve, ms)),
    );

    const payload = buildGetDxPayload(sha, repo, branch, commit.totals, new Date().toISOString());

    await pushCoverageToGetDx(payload, dxToken);

    console.log(
        `Pushed coverage for ${sha} to getDX: ${payload.value.coverage_pct}% ` +
            `(${payload.value.lines_covered}/${payload.value.lines_total} lines)`,
    );
}

if (import.meta.url === `file://${process.argv[1]}`) {
    run().catch((error: unknown) => {
        console.error(error instanceof Error ? error.message : String(error));
        process.exit(1);
    });
}
