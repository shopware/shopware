import { describe, it, mock } from 'node:test';
import assert from 'node:assert/strict';

import {
    CodecovReportNotReadyError,
    HttpError,
    buildGetDxPayload,
    codecovCommitUrl,
    describeError,
    fetchCodecovCommit,
    isRetryable,
    pushCoverageToGetDx,
    waitForCodecovReport,
    withRetry,
    type CodecovCommitResponse,
} from './push-coverage-to-getdx.ts';

const jsonResponse = (body: unknown, ok = true, status = 200) => ({
    ok,
    status,
    json: async () => body,
    text: async () => JSON.stringify(body),
});

describe('codecovCommitUrl', () => {
    it('builds the v2 commit-detail path for the github service', () => {
        assert.equal(
            codecovCommitUrl('shopware', 'shopware', 'abc123'),
            'https://api.codecov.io/api/v2/github/shopware/repos/shopware/commits/abc123/',
        );
    });
});

const networkError = () => new TypeError('fetch failed', { cause: Object.assign(new Error('read ECONNRESET'), { code: 'ECONNRESET' }) });

describe('isRetryable', () => {
    it('retries network errors, timeouts, 429 and 5xx', () => {
        assert.equal(isRetryable(networkError()), true);
        assert.equal(isRetryable(new DOMException('The operation was aborted due to timeout', 'TimeoutError')), true);
        assert.equal(isRetryable(new HttpError('too many requests', 429)), true);
        assert.equal(isRetryable(new HttpError('bad gateway', 502)), true);
    });

    it('does not retry other client errors', () => {
        assert.equal(isRetryable(new HttpError('unauthorized', 401)), false);
        assert.equal(isRetryable(new HttpError('not found', 404)), false);
        assert.equal(isRetryable(new Error('something else')), false);
    });
});

describe('describeError', () => {
    it('includes the cause chain and error codes', () => {
        assert.equal(describeError(networkError()), 'fetch failed <- caused by: read ECONNRESET [ECONNRESET]');
    });

    it('unpacks AggregateError causes', () => {
        const error = new TypeError('fetch failed', {
            cause: new AggregateError([Object.assign(new Error('connect ECONNREFUSED ::1:443'), { code: 'ECONNREFUSED' })], ''),
        });

        assert.equal(
            describeError(error),
            'fetch failed <- caused by: connect ECONNREFUSED ::1:443 [ECONNREFUSED]',
        );
    });
});

describe('withRetry', () => {
    it('retries retryable errors with the given backoff and then succeeds', async () => {
        let call = 0;
        const operation = mock.fn(async () => {
            if (call++ < 2) {
                throw networkError();
            }
            return 'ok';
        });
        const sleep = mock.fn(async (_ms: number) => {});
        const warn = mock.fn((_message: string) => {});

        const result = await withRetry('request', operation, sleep, [1_000, 2_000, 4_000], warn);

        assert.equal(result, 'ok');
        assert.equal(operation.mock.callCount(), 3);
        assert.deepEqual(sleep.mock.calls.map((c) => c.arguments[0]), [1_000, 2_000]);
        assert.match(warn.mock.calls[0].arguments[0], /request failed \(attempt 1\/4\): fetch failed <- caused by: read ECONNRESET \[ECONNRESET\]; retrying in 1s/);
    });

    it('gives up after the last attempt and keeps the original error as cause', async () => {
        const operation = mock.fn(async () => {
            throw networkError();
        });
        const sleep = mock.fn(async () => {});

        await assert.rejects(withRetry('request', operation, sleep, [1, 1, 1], () => {}), (error: Error) => {
            assert.equal(error.message, 'request failed after 4 attempt(s)');
            assert.match(describeError(error), /ECONNRESET/);
            return true;
        });
        assert.equal(operation.mock.callCount(), 4);
    });

    it('does not retry non-retryable errors', async () => {
        const operation = mock.fn(async () => {
            throw new HttpError('unauthorized', 401);
        });
        const sleep = mock.fn(async () => {});

        await assert.rejects(withRetry('request', operation, sleep, [1, 1, 1], () => {}), /failed after 1 attempt/);
        assert.equal(operation.mock.callCount(), 1);
        assert.equal(sleep.mock.callCount(), 0);
    });
});

describe('fetchCodecovCommit', () => {
    it('sends a bearer token and returns the parsed commit', async () => {
        const commit: CodecovCommitResponse = { state: 'complete', totals: { coverage: 78.4, lines: 100, hits: 78 } };
        const fetchImpl = mock.fn(async (_url: string, init?: RequestInit) => {
            assert.equal((init?.headers as Record<string, string>).Authorization, 'Bearer secret');
            return jsonResponse(commit);
        });

        const result = await fetchCodecovCommit('shopware', 'shopware', 'abc123', 'secret', fetchImpl as unknown as typeof fetch);

        assert.deepEqual(result, commit);
    });

    it('throws when Codecov responds with a non-2xx status', async () => {
        const fetchImpl = mock.fn(async () => jsonResponse({}, false, 404));

        await assert.rejects(
            fetchCodecovCommit('shopware', 'shopware', 'abc123', 'secret', fetchImpl as unknown as typeof fetch),
            /Codecov API returned 404/,
        );
    });
});

describe('waitForCodecovReport', () => {
    it('returns as soon as the report is complete', async () => {
        const commit: CodecovCommitResponse = { state: 'complete', totals: { coverage: 78.4, lines: 100, hits: 78 } };
        const fetchCommit = mock.fn(async () => commit);
        const sleep = mock.fn(async () => {});

        const result = await waitForCodecovReport(fetchCommit, sleep);

        assert.deepEqual(result, commit);
        assert.equal(sleep.mock.callCount(), 0);
    });

    it('polls while pending and returns once complete', async () => {
        const pending: CodecovCommitResponse = { state: 'pending', totals: null };
        const complete: CodecovCommitResponse = { state: 'complete', totals: { coverage: 50, lines: 10, hits: 5 } };
        let call = 0;
        const fetchCommit = mock.fn(async () => (call++ === 0 ? pending : complete));
        const sleep = mock.fn(async () => {});

        const result = await waitForCodecovReport(fetchCommit, sleep, [1_000, 1_000]);

        assert.deepEqual(result, complete);
        assert.equal(sleep.mock.callCount(), 1);
        assert.equal(sleep.mock.calls[0].arguments[0], 1_000);
    });

    it('throws immediately on state "error"', async () => {
        const fetchCommit = mock.fn(async (): Promise<CodecovCommitResponse> => ({ state: 'error', totals: null }));
        const sleep = mock.fn(async () => {});

        await assert.rejects(waitForCodecovReport(fetchCommit, sleep, [1_000]), /state: error/);
        assert.equal(sleep.mock.callCount(), 0);
    });

    it('gives up after exhausting the delay list', async () => {
        const fetchCommit = mock.fn(async (): Promise<CodecovCommitResponse> => ({ state: 'pending', totals: null }));
        const sleep = mock.fn(async () => {});

        await assert.rejects(waitForCodecovReport(fetchCommit, sleep, [10, 10]), CodecovReportNotReadyError);
        assert.equal(fetchCommit.mock.callCount(), 3);
        assert.equal(sleep.mock.callCount(), 2);
    });
});

describe('buildGetDxPayload', () => {
    it('maps Codecov totals onto the getDX custom_data shape', () => {
        const payload = buildGetDxPayload(
            'abc123',
            'shopware',
            'trunk',
            { coverage: 78.42345, lines: 15816, hits: 12400 },
            '2026-09-18T12:07:45Z',
        );

        assert.deepEqual(payload, {
            reference: 'abc123',
            key: 'code_coverage',
            timestamp: '2026-09-18T12:07:45Z',
            value: {
                repo: 'shopware',
                branch: 'trunk',
                coverage_pct: 78.42,
                lines_covered: 12400,
                lines_total: 15816,
            },
        });
    });

    it('rejects a "complete" commit with no totals', () => {
        assert.throws(
            () => buildGetDxPayload('abc123', 'shopware', 'trunk', null, '2026-09-18T12:07:45Z'),
            /has no totals/,
        );
    });
});

describe('pushCoverageToGetDx', () => {
    const payload = buildGetDxPayload('abc123', 'shopware', 'trunk', { coverage: 78.4, lines: 100, hits: 78 }, '2026-09-18T12:07:45Z');

    it('POSTs the payload with a bearer token', async () => {
        const fetchImpl = mock.fn(async (url: string, init?: RequestInit) => {
            assert.equal(url, 'https://shopware.getdx.net/api/customData.set');
            assert.equal(init?.method, 'POST');
            assert.equal((init?.headers as Record<string, string>).Authorization, 'Bearer dx-secret');
            assert.equal(init?.body, JSON.stringify(payload));
            return jsonResponse({});
        });

        await pushCoverageToGetDx(payload, 'dx-secret', fetchImpl as unknown as typeof fetch);

        assert.equal(fetchImpl.mock.callCount(), 1);
    });

    it('throws when getDX rejects the payload', async () => {
        const fetchImpl = mock.fn(async () => jsonResponse({ error: 'bad token' }, false, 401));

        await assert.rejects(
            pushCoverageToGetDx(payload, 'dx-secret', fetchImpl as unknown as typeof fetch),
            /getDX customData\.set POST failed with 401/,
        );
    });
});
