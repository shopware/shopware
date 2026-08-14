/**
 * Upload a PHPUnit artifact to Codecov via the Codecov CLI.
 *
 *   node .github/bin/js/codecov-upload.ts coverage coverage.xml [<coverage flags>]
 *   node .github/bin/js/codecov-upload.ts test-results junit.xml
 *
 * The CLI replaces codecov/codecov-action: the runner does not retry a 404
 * while downloading an action, so transient codeload.github.com 404s failed
 * jobs whose test run was green. The download here is retried and the binary
 * is pinned by the SHA256 below — cross-check it against
 * https://cli.codecov.io/<version>/linux/codecov.SHA256SUM on every bump.
 */

import { spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { chmodSync, existsSync, readFileSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

export const CODECOV_CLI_VERSION = 'v11.3.1';
export const CODECOV_CLI_SHA256 = 'ca1d64196d2d34771084afe76ea657d581bf628e31d993ff8e52ea09cc88a56d';

// Uploads only run from the canonical repository: mirrors must not ship
// coverage of unpublished code to a third-party service.
export const UPLOAD_REPOSITORY = 'shopware/shopware';

export const isUploadRepository = (repository: string | undefined): boolean => repository === UPLOAD_REPOSITORY;

export type UploadMode = 'coverage' | 'test-results';

export const isUploadMode = (value: string): value is UploadMode => value === 'coverage' || value === 'test-results';

// --disable-search: auto-discovery used to pick up unrelated *_test.xml files.
// --fail-on-error: without it the CLI exits 0 on a rejected upload, silently
// ungating the Codecov checks.
export const buildArgs = (mode: UploadMode, file: string, flags = ''): string[] =>
    mode === 'coverage'
        ? ['upload-process', '--disable-search', '--fail-on-error', '-f', file, ...(flags === '' ? [] : ['-F', flags])]
        : ['do-upload', '--report-type', 'test_results', '--disable-search', '--fail-on-error', '-f', file];

export const sha256 = (data: Uint8Array): string => createHash('sha256').update(data).digest('hex');

type Sleep = (ms: number) => Promise<void>;

const sleep: Sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

export type RetryOptions = {
    attempts: number;
    /** Backoff base: attempt n waits n * delayMs before the next try. */
    delayMs: number;
    wait?: Sleep;
};

const DOWNLOAD_RETRY: RetryOptions = { attempts: 5, delayMs: 2_000 };
const UPLOAD_RETRY: RetryOptions = { attempts: 3, delayMs: 10_000 };

export const withRetries = async <T>(
    fn: () => Promise<T>,
    label: string,
    { attempts, delayMs, wait = sleep }: RetryOptions,
    warn: (message: string) => void = (message) => process.stderr.write(`${message}\n`),
): Promise<T> => {
    let lastError = 'no attempt made';

    for (let attempt = 1; attempt <= attempts; attempt++) {
        try {
            return await fn();
        } catch (error) {
            lastError = error instanceof Error ? error.message : String(error);
            warn(`${label} failed (attempt ${attempt}/${attempts}): ${lastError}`);

            if (attempt < attempts) {
                await wait(attempt * delayMs);
            }
        }
    }

    throw new Error(`${label} failed after ${attempts} attempts: ${lastError}`);
};

/**
 * A checksum mismatch is retried like any other failure — on a fresh download
 * it is far more likely a truncated response than an attack — but unverified
 * data is never returned.
 */
export const downloadVerified = (
    url: string,
    expectedSha256: string,
    retryOptions: RetryOptions,
    fetchImpl: typeof fetch = fetch,
): Promise<Uint8Array> =>
    withRetries(
        async () => {
            const response = await fetchImpl(url);

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = new Uint8Array(await response.arrayBuffer());
            const actual = sha256(data);

            if (actual !== expectedSha256) {
                throw new Error(`checksum mismatch: expected ${expectedSha256}, got ${actual}`);
            }

            return data;
        },
        `download of ${url}`,
        retryOptions,
    );

/** Download once per job: later invocations reuse the verified binary. */
const ensureCli = async (): Promise<string> => {
    const cliPath = join(process.env.RUNNER_TEMP ?? tmpdir(), `codecov-cli-${CODECOV_CLI_VERSION}`);

    if (existsSync(cliPath) && sha256(readFileSync(cliPath)) === CODECOV_CLI_SHA256) {
        return cliPath;
    }

    process.stderr.write(`Downloading Codecov CLI ${CODECOV_CLI_VERSION}\n`);
    const data = await downloadVerified(
        `https://cli.codecov.io/${CODECOV_CLI_VERSION}/linux/codecov`,
        CODECOV_CLI_SHA256,
        DOWNLOAD_RETRY,
    );

    writeFileSync(cliPath, data);
    chmodSync(cliPath, 0o755);

    return cliPath;
};

const main = async (): Promise<void> => {
    const [mode, file, flags] = process.argv.slice(2);

    if (mode === undefined || file === undefined || !isUploadMode(mode)) {
        process.stderr.write('usage: codecov-upload.ts <coverage|test-results> <file> [<coverage flags>]\n');
        process.exit(2);
    }

    if (!isUploadRepository(process.env.GITHUB_REPOSITORY)) {
        process.stderr.write(
            `Skipping Codecov upload: '${process.env.GITHUB_REPOSITORY ?? ''}' is not ${UPLOAD_REPOSITORY}.\n`,
        );

        return;
    }

    if (!process.env.CODECOV_TOKEN) {
        throw new Error('CODECOV_TOKEN is not set.');
    }

    if (!existsSync(file)) {
        throw new Error(`report file '${file}' does not exist — nothing to upload.`);
    }

    if (process.platform !== 'linux') {
        throw new Error('only Linux runners are supported (the pinned binary is linux/amd64).');
    }

    const args = buildArgs(mode, file, flags ?? '');
    const cliPath = await ensureCli();

    await withRetries(
        async () => {
            const { status } = spawnSync(cliPath, args, { stdio: 'inherit' });

            if (status !== 0) {
                throw new Error(`exit code ${status ?? 'unknown'}`);
            }
        },
        'Codecov upload',
        UPLOAD_RETRY,
    );
};

// Run the script if called directly
if (import.meta.url === `file://${process.argv[1]}`) {
    try {
        await main();
    } catch (error) {
        process.stderr.write(`Error: ${error instanceof Error ? error.message : String(error)}\n`);
        process.exit(1);
    }
}
