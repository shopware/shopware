/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import { parseArgs } from 'node:util';
import { createDiff } from '../diff/create-diff';
import { SNAPSHOT_FORMAT_VERSION, type ApiSnapshot } from '../snapshot/types';
import { EXIT_OK, UsageError, type CliCommand } from './types';

export const EXIT_BREAKING = 3;

function readSnapshot(file: string): ApiSnapshot {
    const snapshot = JSON.parse(fs.readFileSync(path.resolve(file), 'utf8')) as Partial<ApiSnapshot> | null;

    if (
        typeof snapshot !== 'object' ||
        snapshot === null ||
        snapshot.formatVersion !== SNAPSHOT_FORMAT_VERSION ||
        !Array.isArray(snapshot.components) ||
        typeof snapshot.shopwareObject !== 'object' ||
        snapshot.shopwareObject === null ||
        !Array.isArray(snapshot.diagnostics)
    ) {
        throw new Error(`${file} is not an API snapshot of format version ${SNAPSHOT_FORMAT_VERSION}.`);
    }

    return snapshot as ApiSnapshot;
}

export const diffCommand: CliCommand = {
    summary: 'Compare two API snapshots and classify every change.',
    usage: [
        'diff --base <snapshot.json> --head <snapshot.json> [--out <file>] [--fail-on-breaking]',
        '',
        '  --base              Snapshot of the state to compare against, e.g. trunk',
        '  --head              Snapshot of the changed state, e.g. the working tree',
        '  --out               Write the JSON to this file instead of stdout',
        `  --fail-on-breaking  Exit with ${EXIT_BREAKING} when the diff contains a breaking change`,
    ].join('\n'),

    run(args, io) {
        const { values, positionals } = parseArgs({
            args,
            options: {
                base: { type: 'string' },
                head: { type: 'string' },
                out: { type: 'string' },
                'fail-on-breaking': { type: 'boolean' },
            },
            strict: true,
            allowPositionals: true,
        });

        if (positionals.length > 0) {
            throw new UsageError(`Unexpected argument: ${positionals[0]}`);
        }

        const { base, head, out } = values;

        if (!base || !head) {
            throw new UsageError(`--${base ? 'head' : 'base'} is required`);
        }

        if (out === '') {
            throw new UsageError('--out must not be empty');
        }

        const diff = createDiff(readSnapshot(base), readSnapshot(head));
        const json = `${JSON.stringify(diff, null, 4)}\n`;

        if (out === undefined) {
            io.stdout(json);
        } else {
            const outFile = path.resolve(out);
            fs.mkdirSync(path.dirname(outFile), { recursive: true });
            fs.writeFileSync(outFile, json);
        }

        return Promise.resolve(values['fail-on-breaking'] && diff.summary.hasBreaking ? EXIT_BREAKING : EXIT_OK);
    },
};
