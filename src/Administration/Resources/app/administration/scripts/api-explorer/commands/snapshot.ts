/**
 * @sw-package framework
 */

import * as fs from 'node:fs';
import * as path from 'node:path';
import { parseArgs } from 'node:util';
import { findRepoRoot } from '../git';
import { createSnapshotService } from '../service/snapshot-service';
import { createSnapshot } from '../snapshot/create-snapshot';
import { WORKTREE_REF, type ApiSnapshot } from '../snapshot/types';
import { EXIT_OK, UsageError, type CliCommand } from './types';

const DEFAULT_ADMIN_ROOT = path.resolve(__dirname, '..', '..', '..');

async function snapshotRef(adminRoot: string, ref: string, refresh: boolean): Promise<ApiSnapshot> {
    const service = createSnapshotService({ repoRoot: await findRepoRoot(adminRoot), adminRoot });

    return service.getSnapshot(ref, { refresh });
}

export const snapshotCommand: CliCommand = {
    summary: 'Extract the public extension API of the Administration into a JSON snapshot.',
    usage: [
        'snapshot [--root <adminRoot>] [--ref <ref>] [--no-cache] [--commit <sha>] [--out <file>]',
        '',
        '  --root      Administration root to extract (default: this Administration)',
        `  --ref       ${WORKTREE_REF} (default) or a git ref; a git ref is extracted from the`,
        '              repository of --root and cached in var/cache/api-explorer',
        '  --no-cache  Extract the git ref again instead of using the cache',
        '  --commit    Extract --root as is and record --ref and this sha (used for child processes)',
        '  --out       Write the JSON to this file instead of stdout',
    ].join('\n'),

    async run(args, io) {
        const { values, positionals } = parseArgs({
            args,
            options: {
                root: { type: 'string' },
                ref: { type: 'string' },
                commit: { type: 'string' },
                'no-cache': { type: 'boolean' },
                out: { type: 'string' },
            },
            strict: true,
            allowPositionals: true,
        });

        if (positionals.length > 0) {
            throw new UsageError(`Unexpected argument: ${positionals[0]}`);
        }

        for (const [
            name,
            value,
        ] of Object.entries(values)) {
            if (value === '') {
                throw new UsageError(`--${name} must not be empty`);
            }
        }

        const adminRoot = path.resolve(values.root ?? DEFAULT_ADMIN_ROOT);
        const ref = values.ref ?? WORKTREE_REF;

        if (values.commit !== undefined && ref === WORKTREE_REF) {
            throw new UsageError(`--commit requires a --ref other than ${WORKTREE_REF}`);
        }

        if (values.commit !== undefined && values['no-cache']) {
            throw new UsageError('--commit and --no-cache cannot be combined');
        }

        const snapshot =
            ref === WORKTREE_REF || values.commit !== undefined
                ? await createSnapshot({ adminRoot, ref, commit: values.commit ?? null })
                : await snapshotRef(adminRoot, ref, values['no-cache'] === true);
        const json = `${JSON.stringify(snapshot, null, 4)}\n`;

        if (values.out === undefined) {
            io.stdout(json);
        } else {
            const outFile = path.resolve(values.out);
            fs.mkdirSync(path.dirname(outFile), { recursive: true });
            fs.writeFileSync(outFile, json);
        }

        return EXIT_OK;
    },
};
