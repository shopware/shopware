import assert from 'node:assert/strict';
import { test } from 'node:test';
import { checkPhpExtensions, missingExtensions } from './php-extensions.ts';

const REQUIRED = ['imagick', 'pdo_sqlite', 'zstd'];

const recordingCore = () => {
    const calls: { failed: string[]; info: string[] } = { failed: [], info: [] };

    return {
        calls,
        core: {
            setFailed: (message: string) => calls.failed.push(message),
            info: (message: string) => calls.info.push(message),
        },
    };
};

test('missingExtensions is empty when every required extension is loaded', () => {
    assert.deepEqual(missingExtensions(['Core', 'imagick', 'pdo_sqlite', 'zstd'], REQUIRED), []);
});

test('missingExtensions lists each extension that is not loaded, in the required order', () => {
    assert.deepEqual(missingExtensions(['Core', 'imagick'], REQUIRED), ['pdo_sqlite', 'zstd']);
});

test('missingExtensions compares names case-insensitively', () => {
    assert.deepEqual(missingExtensions(['IMAGICK', 'PDO_SQLite', 'Zstd'], REQUIRED), []);
});

test('checkPhpExtensions fails the job and names the missing extensions', () => {
    const { calls, core } = recordingCore();

    checkPhpExtensions(core, REQUIRED, ['imagick']);

    assert.deepEqual(calls.failed, ['PHP extensions not loaded: pdo_sqlite, zstd']);
    assert.deepEqual(calls.info, []);
});

test('checkPhpExtensions passes when every extension is loaded', () => {
    const { calls, core } = recordingCore();

    checkPhpExtensions(core, REQUIRED, ['imagick', 'pdo_sqlite', 'zstd']);

    assert.deepEqual(calls.failed, []);
    assert.deepEqual(calls.info, ['PHP extensions loaded: imagick, pdo_sqlite, zstd']);
});
