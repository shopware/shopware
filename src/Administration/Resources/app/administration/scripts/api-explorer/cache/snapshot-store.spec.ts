/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as os from 'node:os';
import * as path from 'node:path';
import { EXTRACTOR_VERSION, SNAPSHOT_FORMAT_VERSION, type ApiSnapshot } from '../snapshot/types';
import { createSnapshotStore, type SnapshotStore } from './snapshot-store';

const COMMIT = 'a'.repeat(40);
const ENVIRONMENT = 'e1e2e3e4e5e6';

function snapshotOf(commit: string, overrides: Partial<ApiSnapshot> = {}): ApiSnapshot {
    return {
        formatVersion: SNAPSHOT_FORMAT_VERSION,
        extractorVersion: EXTRACTOR_VERSION,
        ref: 'v1',
        commit,
        createdAt: '2026-10-21T00:00:00.000Z',
        shopwareObject: {
            path: 'Shopware',
            name: 'Shopware',
            kind: 'object',
            signature: 'object',
            callSignatures: [],
            typeSource: 'typescript',
            truncated: false,
            children: [],
            visibility: 'public',
            deprecation: null,
            location: null,
        },
        components: [],
        diagnostics: [],
        ...overrides,
    };
}

describe('api-explorer snapshot store', () => {
    let repoRoot: string;
    let store: SnapshotStore;

    beforeEach(() => {
        repoRoot = fs.mkdtempSync(path.join(os.tmpdir(), 'api-explorer-store-'));
        store = createSnapshotStore(repoRoot);
    });

    afterEach(() => {
        fs.rmSync(repoRoot, { recursive: true, force: true });
    });

    it('keys entries by commit and extractor version below var/cache/api-explorer', () => {
        expect(store.filePath(COMMIT, ENVIRONMENT)).toBe(
            path.join(repoRoot, 'var/cache/api-explorer/snapshots', `${COMMIT}-${EXTRACTOR_VERSION}-${ENVIRONMENT}.json`),
        );
    });

    it('misses for an unknown commit', async () => {
        await expect(store.read(COMMIT, ENVIRONMENT)).resolves.toBeNull();
    });

    it('returns a written snapshot and leaves no temporary files behind', async () => {
        const snapshot = snapshotOf(COMMIT);

        await store.write(COMMIT, ENVIRONMENT, snapshot);

        await expect(store.read(COMMIT, ENVIRONMENT)).resolves.toEqual(snapshot);
        expect(fs.readdirSync(store.directory)).toEqual([`${COMMIT}-${EXTRACTOR_VERSION}-${ENVIRONMENT}.json`]);
    });

    it('replaces an existing entry', async () => {
        await store.write(COMMIT, ENVIRONMENT, snapshotOf(COMMIT, { ref: 'old' }));
        await store.write(COMMIT, ENVIRONMENT, snapshotOf(COMMIT, { ref: 'new' }));

        await expect(store.read(COMMIT, ENVIRONMENT)).resolves.toMatchObject({ ref: 'new' });
    });

    it.each([
        ['truncated JSON', '{"formatVersion": 1, "comp'],
        ['an empty file', ''],
        ['a JSON value of the wrong shape', '[1, 2, 3]'],
        ['another commit', JSON.stringify(snapshotOf('b'.repeat(40)))],
        ['another format version', JSON.stringify(snapshotOf(COMMIT, { formatVersion: SNAPSHOT_FORMAT_VERSION + 1 }))],
    ])('misses for an entry with %s', async (_case, content) => {
        fs.mkdirSync(store.directory, { recursive: true });
        fs.writeFileSync(store.filePath(COMMIT, ENVIRONMENT), content);

        await expect(store.read(COMMIT, ENVIRONMENT)).resolves.toBeNull();
    });

    it('misses for entries of another extractor version', async () => {
        const otherStore = createSnapshotStore(repoRoot, { extractorVersion: 'other' });
        await otherStore.write(COMMIT, ENVIRONMENT, snapshotOf(COMMIT, { extractorVersion: 'other' }));

        await expect(store.read(COMMIT, ENVIRONMENT)).resolves.toBeNull();
    });

    it('refuses snapshots that do not match the key', async () => {
        await expect(store.write(COMMIT, ENVIRONMENT, snapshotOf('b'.repeat(40)))).rejects.toThrow('cannot be stored');
        await expect(store.write(COMMIT, ENVIRONMENT, snapshotOf(COMMIT, { extractorVersion: 'other' }))).rejects.toThrow(
            'cannot be stored',
        );
    });

    it('misses for entries of another environment', async () => {
        await store.write(COMMIT, ENVIRONMENT, snapshotOf(COMMIT));

        await expect(store.read(COMMIT, 'f0f0f0f0f0f0')).resolves.toBeNull();
    });

    it('rejects keys that are no commit sha or hex environment key', () => {
        expect(() => store.filePath('../../etc/passwd', ENVIRONMENT)).toThrow('Expected a full commit sha');
        expect(() => store.filePath(COMMIT, '../x')).toThrow('Expected a hex environment key');
    });
});
