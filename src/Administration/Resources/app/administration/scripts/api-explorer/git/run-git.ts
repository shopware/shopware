/**
 * @sw-package framework
 */

import { spawn, type ChildProcess } from 'node:child_process';
import { once } from 'node:events';
import type { Readable } from 'node:stream';

export class GitCommandError extends Error {
    constructor(
        readonly args: string[],
        readonly exitCode: number | null,
        readonly stderr: string,
    ) {
        super(`git ${args.join(' ')} failed (exit code ${String(exitCode)}): ${stderr.trim()}`);
    }
}

export interface ProcessResult {
    exitCode: number | null;
    signal: NodeJS.Signals | null;
}

// Set inside git hooks; they would point every command at the user's repository instead of `cwd`.
const GIT_LOCATION_VARIABLES = [
    'GIT_DIR',
    'GIT_WORK_TREE',
    'GIT_INDEX_FILE',
    'GIT_OBJECT_DIRECTORY',
    'GIT_ALTERNATE_OBJECT_DIRECTORIES',
    'GIT_COMMON_DIR',
    'GIT_NAMESPACE',
    'GIT_PREFIX',
];

export function isolatedGitEnv(): NodeJS.ProcessEnv {
    const env = { ...process.env };

    GIT_LOCATION_VARIABLES.forEach((name) => delete env[name]);

    return env;
}

// GIT_OPTIONAL_LOCKS=0 keeps `git status` from refreshing the index of the user's checkout.
const GIT_ENV: NodeJS.ProcessEnv = {
    ...isolatedGitEnv(),
    GIT_OPTIONAL_LOCKS: '0',
    GIT_TERMINAL_PROMPT: '0',
    LC_ALL: 'C',
};

export interface SpawnGitOptions {
    withStdin?: boolean;
    /** Kills git with SIGTERM once exceeded. */
    timeoutMs?: number;
}

export function spawnGit(
    repoRoot: string,
    args: string[],
    { withStdin = false, timeoutMs }: SpawnGitOptions = {},
): ChildProcess {
    return spawn('git', args, {
        cwd: repoRoot,
        env: GIT_ENV,
        stdio: [withStdin ? 'pipe' : 'ignore', 'pipe', 'pipe'],
        shell: false,
        timeout: timeoutMs,
    });
}

export async function readStreamBuffer(stream: Readable | null): Promise<Buffer> {
    const chunks: Buffer[] = [];

    if (stream) {
        for await (const chunk of stream) {
            chunks.push(chunk as Buffer);
        }
    }

    return Buffer.concat(chunks);
}

export async function readStream(stream: Readable | null): Promise<string> {
    return (await readStreamBuffer(stream)).toString('utf8');
}

/** Rejects when the process cannot be spawned. */
export async function waitForExit(child: ChildProcess): Promise<ProcessResult> {
    const [
        exitCode,
        signal,
    ] = (await once(child, 'close')) as [number | null, NodeJS.Signals | null];

    return { exitCode, signal };
}

export interface RunGitOptions {
    timeoutMs?: number;
}

export async function runGitBuffer(
    repoRoot: string,
    args: string[],
    input?: string,
    { timeoutMs }: RunGitOptions = {},
): Promise<Buffer> {
    const child = spawnGit(repoRoot, args, { withStdin: input !== undefined, timeoutMs });

    // A git that exits early closes stdin; its exit code reports that.
    child.stdin?.end(input);
    child.stdin?.once('error', () => undefined);

    const [
        stdout,
        stderr,
        { exitCode, signal },
    ] = await Promise.all([
        readStreamBuffer(child.stdout),
        readStream(child.stderr),
        waitForExit(child),
    ]);

    if (exitCode !== 0) {
        const timedOut = signal !== null && timeoutMs !== undefined;

        throw new GitCommandError(args, exitCode, timedOut ? `timed out after ${timeoutMs} ms\n${stderr}` : stderr);
    }

    return stdout;
}

export async function runGit(repoRoot: string, args: string[], input?: string, options?: RunGitOptions): Promise<string> {
    return (await runGitBuffer(repoRoot, args, input, options)).toString('utf8');
}
