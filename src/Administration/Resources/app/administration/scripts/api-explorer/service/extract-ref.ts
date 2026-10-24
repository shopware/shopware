/**
 * @sw-package framework
 */

import { spawn } from 'node:child_process';
import * as fs from 'node:fs';
import * as os from 'node:os';
import * as path from 'node:path';
import { readStream, waitForExit } from '../git/run-git';
import type { ApiSnapshot } from '../snapshot/types';

export interface ExtractRefRequest {
    ref: string;
    commit: string;
    /** Root of the materialized tree, not the checkout. */
    adminRoot: string;
}

export type ExtractRef = (request: ExtractRefRequest) => Promise<ApiSnapshot>;

const CLI_FILE = path.resolve(__dirname, '..', 'cli.ts');
const TOOL_ROOT = path.resolve(__dirname, '..', '..', '..');
const STDERR_LIMIT = 8192;

/** Runs the snapshot command in a child process, so the ~1.3 GB program of a ref is freed when it exits. */
export const extractRefInChildProcess: ExtractRef = async ({ ref, commit, adminRoot }) => {
    const outDirectory = fs.mkdtempSync(path.join(os.tmpdir(), 'api-explorer-out-'));
    const outFile = path.join(outDirectory, 'snapshot.json');

    try {
        const child = spawn(
            process.execPath,
            [
                require.resolve('ts-node/dist/bin.js', { paths: [TOOL_ROOT] }),
                '--transpileOnly',
                CLI_FILE,
                'snapshot',
                '--root',
                adminRoot,
                '--ref',
                ref,
                '--commit',
                commit,
                '--out',
                outFile,
            ],
            { cwd: TOOL_ROOT, stdio: ['ignore', 'ignore', 'pipe'], shell: false },
        );
        const [
            stderr,
            { exitCode, signal },
        ] = await Promise.all([
            readStream(child.stderr),
            waitForExit(child),
        ]);

        if (exitCode !== 0) {
            const reason = signal ?? `exit code ${String(exitCode)}`;
            throw new Error(`Extracting ${ref} (${commit}) failed with ${reason}:\n${stderr.trim().slice(-STDERR_LIMIT)}`);
        }

        return JSON.parse(fs.readFileSync(outFile, 'utf8')) as ApiSnapshot;
    } finally {
        fs.rmSync(outDirectory, { recursive: true, force: true });
    }
};
