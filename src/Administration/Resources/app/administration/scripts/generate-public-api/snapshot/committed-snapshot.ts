/**
 * @sw-package framework
 *
 * The snapshot of this Administration checkout: how it is generated, and how it is read from and written to
 * `public-api/`, where it is committed.
 */
import fs from 'node:fs';
import path from 'node:path';
import { ADMINISTRATION_ROOT, currentMajorFlags } from '../source/checkout';
import { generateSnapshot } from './snapshot';
import type { Snapshot, SnapshotFiles } from './snapshot';

const SNAPSHOT_DIRECTORY = path.join(ADMINISTRATION_ROOT, 'public-api');

const GENERATED_FILE = /(?:\.api\.md|^index\.json)$/;

/** @private */
export function generateCurrentSnapshot(): Snapshot {
    return generateSnapshot(ADMINISTRATION_ROOT, { majorFlags: currentMajorFlags() });
}

function committedFiles(): string[] {
    if (!fs.existsSync(SNAPSHOT_DIRECTORY)) {
        return [];
    }

    return fs
        .readdirSync(SNAPSHOT_DIRECTORY, { recursive: true, encoding: 'utf8' })
        .map((file) => file.split(path.sep).join('/'))
        .filter((file) => GENERATED_FILE.test(file));
}

/** @private */
export function readCommittedSnapshot(): SnapshotFiles {
    return {
        files: Object.fromEntries(
            committedFiles().map((file) => [
                file,
                fs.readFileSync(path.join(SNAPSHOT_DIRECTORY, file), 'utf8'),
            ]),
        ),
    };
}

/**
 * @private
 *
 * Writes the snapshot and removes the generated files it no longer contains.
 */
export function writeCommittedSnapshot({ files }: SnapshotFiles): void {
    committedFiles()
        .filter((file) => !Object.hasOwn(files, file))
        .forEach((file) => fs.rmSync(path.join(SNAPSHOT_DIRECTORY, file)));

    Object.entries(files).forEach(([file, content]) => {
        const target = path.join(SNAPSHOT_DIRECTORY, file);

        fs.mkdirSync(path.dirname(target), { recursive: true });
        fs.writeFileSync(target, content);
    });
}
