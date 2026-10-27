/**
 * @sw-package framework
 */

import { randomBytes } from 'node:crypto';
import * as fs from 'node:fs/promises';
import * as path from 'node:path';
import { EXTRACTOR_VERSION, SNAPSHOT_FORMAT_VERSION, type ApiSnapshot } from '../snapshot/types';

export interface SnapshotStore {
    /** Absolute */
    directory: string;
    /** `environmentKey` identifies everything besides the commit the snapshot was extracted against. */
    filePath(commit: string, environmentKey: string): string;
    /** Null when there is no entry or it is unreadable, corrupt or from another extractor or format version. */
    read(commit: string, environmentKey: string): Promise<ApiSnapshot | null>;
    /** Atomic: concurrent readers see either the old entry or the complete new one. */
    write(commit: string, environmentKey: string, snapshot: ApiSnapshot): Promise<void>;
}

export function snapshotCacheDirectory(repoRoot: string): string {
    return path.join(repoRoot, 'var', 'cache', 'api-explorer', 'snapshots');
}

function assertKey(commit: string, environmentKey: string): void {
    if (!/^[0-9a-f]{40}$|^[0-9a-f]{64}$/.test(commit)) {
        throw new Error(`Expected a full commit sha, got ${JSON.stringify(commit)}`);
    }

    if (!/^[0-9a-f]{1,64}$/.test(environmentKey)) {
        throw new Error(`Expected a hex environment key, got ${JSON.stringify(environmentKey)}`);
    }
}

function isUsableSnapshot(value: unknown, commit: string, extractorVersion: string): value is ApiSnapshot {
    if (typeof value !== 'object' || value === null) {
        return false;
    }

    const snapshot = value as Partial<ApiSnapshot>;

    return (
        snapshot.formatVersion === SNAPSHOT_FORMAT_VERSION &&
        snapshot.extractorVersion === extractorVersion &&
        snapshot.commit === commit &&
        typeof snapshot.ref === 'string' &&
        typeof snapshot.shopwareObject === 'object' &&
        snapshot.shopwareObject !== null &&
        Array.isArray(snapshot.components) &&
        Array.isArray(snapshot.diagnostics)
    );
}

export function createSnapshotStore(
    repoRoot: string,
    { extractorVersion = EXTRACTOR_VERSION }: { extractorVersion?: string } = {},
): SnapshotStore {
    const directory = snapshotCacheDirectory(repoRoot);

    const filePath = (commit: string, environmentKey: string): string => {
        assertKey(commit, environmentKey);

        return path.join(directory, `${commit}-${extractorVersion}-${environmentKey}.json`);
    };

    return {
        directory,
        filePath,

        async read(commit, environmentKey) {
            let parsed: unknown;

            try {
                parsed = JSON.parse(await fs.readFile(filePath(commit, environmentKey), 'utf8'));
            } catch {
                return null;
            }

            return isUsableSnapshot(parsed, commit, extractorVersion) ? parsed : null;
        },

        async write(commit, environmentKey, snapshot) {
            if (snapshot.commit !== commit || snapshot.extractorVersion !== extractorVersion) {
                throw new Error(
                    `Snapshot of ${String(snapshot.commit)} (extractor ${snapshot.extractorVersion}) cannot be stored as ${commit} (extractor ${extractorVersion})`,
                );
            }

            const target = filePath(commit, environmentKey);
            const temporary = `${target}.${process.pid}.${randomBytes(6).toString('hex')}.tmp`;

            await fs.mkdir(directory, { recursive: true });

            try {
                await fs.writeFile(temporary, JSON.stringify(snapshot));
                await fs.rename(temporary, target);
            } catch (error) {
                await fs.rm(temporary, { force: true });
                throw error;
            }
        },
    };
}
