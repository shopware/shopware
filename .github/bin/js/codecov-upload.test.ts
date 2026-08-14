import assert from 'node:assert/strict';
import { test } from 'node:test';
import { buildArgs, downloadVerified, isUploadMode, isUploadRepository, sha256, withRetries } from './codecov-upload.ts';
import type { RetryOptions } from './codecov-upload.ts';

const noWait = async (): Promise<void> => {};
const noWarn = (): void => {};

const retry = (attempts: number, overrides: Partial<RetryOptions> = {}): RetryOptions => ({
    attempts,
    delayMs: 1,
    wait: noWait,
    ...overrides,
});

const body = new TextEncoder().encode('binary payload');
const bodySha = sha256(body);

const responseWith = (status: number, data: Uint8Array = body): Response =>
    new Response(status === 404 ? 'not found' : Buffer.from(data), { status });

test('isUploadRepository accepts only the canonical repository, keeping mirrors from uploading', () => {
    assert.equal(isUploadRepository('shopware/shopware'), true);
    assert.equal(isUploadRepository('shopware/shopware-mirror'), false);
    assert.equal(isUploadRepository(undefined), false);
});

test('isUploadMode accepts exactly the two upload modes', () => {
    assert.equal(isUploadMode('coverage'), true);
    assert.equal(isUploadMode('test-results'), true);
    assert.equal(isUploadMode('bogus'), false);
});

test('buildArgs assembles a blocking coverage upload without flags', () => {
    assert.deepEqual(buildArgs('coverage', 'coverage.xml'), [
        'upload-process',
        '--disable-search',
        '--fail-on-error',
        '-f',
        'coverage.xml',
    ]);
});

test('buildArgs appends -F only when coverage flags are given', () => {
    assert.deepEqual(buildArgs('coverage', 'coverage.xml', 'phpunit-unit'), [
        'upload-process',
        '--disable-search',
        '--fail-on-error',
        '-f',
        'coverage.xml',
        '-F',
        'phpunit-unit',
    ]);
});

test('buildArgs appends --disable-file-fixes only on request', () => {
    assert.deepEqual(buildArgs('coverage', 'coverage.xml', 'jest-admin', true), [
        'upload-process',
        '--disable-search',
        '--fail-on-error',
        '-f',
        'coverage.xml',
        '-F',
        'jest-admin',
        '--disable-file-fixes',
    ]);
    assert.ok(!buildArgs('coverage', 'coverage.xml', 'jest-admin').includes('--disable-file-fixes'));
});

test('buildArgs assembles a test-results upload', () => {
    assert.deepEqual(buildArgs('test-results', 'junit.xml'), [
        'do-upload',
        '--report-type',
        'test_results',
        '--disable-search',
        '--fail-on-error',
        '-f',
        'junit.xml',
    ]);
});

test('sha256 matches the well-known empty-input digest', () => {
    assert.equal(sha256(new Uint8Array()), 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855');
});

test('withRetries returns the first successful result without warning', async () => {
    const warnings: string[] = [];

    const result = await withRetries(async () => 'ok', 'op', retry(3), (message) => warnings.push(message));

    assert.equal(result, 'ok');
    assert.deepEqual(warnings, []);
});

test('withRetries succeeds after a transient failure and warns once', async () => {
    let calls = 0;
    const warnings: string[] = [];
    const flaky = async (): Promise<string> => {
        calls += 1;

        if (calls === 1) {
            throw new Error('transient');
        }

        return 'ok';
    };

    const result = await withRetries(flaky, 'op', retry(3), (message) => warnings.push(message));

    assert.equal(result, 'ok');
    assert.equal(calls, 2);
    assert.deepEqual(warnings, ['op failed (attempt 1/3): transient']);
});

test('withRetries throws the labelled last error after exhausting attempts', async () => {
    let calls = 0;
    const down = async (): Promise<never> => {
        calls += 1;
        throw new Error('connection reset');
    };

    await assert.rejects(
        withRetries(down, 'download', retry(5), noWarn),
        /download failed after 5 attempts: connection reset/,
    );
    assert.equal(calls, 5);
});

test('withRetries backs off by attempt * delayMs and skips the final wait', async () => {
    const waits: number[] = [];
    const recordWait = async (ms: number): Promise<void> => {
        waits.push(ms);
    };
    const down = async (): Promise<never> => {
        throw new Error('boom');
    };

    await assert.rejects(withRetries(down, 'op', retry(3, { delayMs: 100, wait: recordWait }), noWarn));

    assert.deepEqual(waits, [100, 200]);
});

test('downloadVerified returns the payload when download and checksum succeed', async () => {
    const data = await downloadVerified('https://example.test/cli', bodySha, retry(1), async () => responseWith(200));

    assert.deepEqual(data, body);
});

test('downloadVerified retries a 404 — the failure mode the runner does not retry', async () => {
    let calls = 0;
    const flaky = async (): Promise<Response> => {
        calls += 1;

        return calls === 1 ? responseWith(404) : responseWith(200);
    };

    const data = await downloadVerified('https://example.test/cli', bodySha, retry(3), flaky);

    assert.deepEqual(data, body);
    assert.equal(calls, 2);
});

test('downloadVerified never returns data that fails the checksum', async () => {
    let calls = 0;
    const tampered = async (): Promise<Response> => {
        calls += 1;

        return responseWith(200, new TextEncoder().encode('tampered payload'));
    };

    await assert.rejects(
        downloadVerified('https://example.test/cli', bodySha, retry(3), tampered),
        /failed after 3 attempts: checksum mismatch/,
    );
    assert.equal(calls, 3);
});
