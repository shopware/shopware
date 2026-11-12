/**
 * @sw-package framework
 *
 * The snapshot of this Administration checkout: how it is generated, and how it is read from and written to
 * `public-api/`, where it is committed.
 */
import fs from 'node:fs';
import path from 'node:path';
import { loadFeatureConfig } from '../../test/_helper_/majorFeatureFlags';
import type { FeatureConfig } from '../../test/_helper_/majorFeatureFlags';
import { isSameFlag, versionOfFlag } from './flag-states';
import { generateSnapshot } from './snapshot';
import type { Snapshot } from './snapshot';

/** @private */
export const ADMINISTRATION_ROOT = path.resolve(__dirname, '../..');

const SNAPSHOT_DIRECTORY = path.join(ADMINISTRATION_ROOT, 'public-api');

const GENERATED_FILE = /(?:\.d\.ts|\.api\.md|^index\.json)$/;

/**
 * @private
 *
 * The flags that are on in the major state: the version flag of the next major, which is the lowest one that
 * is still off, and every flag that names it as its `major`.
 */
export function majorFlagsOf(config: FeatureConfig): string[] {
    const flags = config.shopware?.feature?.flags ?? [];
    const [nextMajor] = flags
        .filter((flag) => flag.default !== true)
        .flatMap(({ name }) => {
            const version = versionOfFlag(name);

            return version ? [{ name, version }] : [];
        })
        .sort((a, b) => a.version[0] - b.version[0] || a.version[1] - b.version[1]);

    if (nextMajor === undefined) {
        throw new Error('feature.yaml has no version flag that is still off, so the next major is unknown.');
    }

    return [
        nextMajor.name,
        ...flags.filter(({ major }) => major !== undefined && isSameFlag(major, nextMajor.name)).map(({ name }) => name),
    ];
}

/** @private */
export function generateCurrentSnapshot(): Snapshot {
    return generateSnapshot(ADMINISTRATION_ROOT, { majorFlags: majorFlagsOf(loadFeatureConfig()) });
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
export function readCommittedSnapshot(): Snapshot {
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
export function writeCommittedSnapshot({ files }: Snapshot): void {
    committedFiles()
        .filter((file) => !Object.hasOwn(files, file))
        .forEach((file) => fs.rmSync(path.join(SNAPSHOT_DIRECTORY, file)));

    Object.entries(files).forEach(([file, content]) => {
        const target = path.join(SNAPSHOT_DIRECTORY, file);

        fs.mkdirSync(path.dirname(target), { recursive: true });
        fs.writeFileSync(target, content);
    });
}
