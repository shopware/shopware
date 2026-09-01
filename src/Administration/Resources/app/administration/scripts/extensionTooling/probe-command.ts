/**
 * @sw-package framework
 *
 * Child-process runner for the live probes. Wraps `execFile` so a failed run
 * (non-zero exit or timeout) resolves into a structured {@link CommandResult}
 * instead of a rejected promise every caller would have to unwrap.
 */

import { execFile } from 'child_process';
import { promisify } from 'util';

const execFileAsync = promisify(execFile);

export const PROCESS_TIMEOUT_MS = 10 * 60 * 1000;
const MAX_BUFFER = 100 * 1024 * 1024;

export interface CommandResult {
    status: number;
    /** Both streams merged — what a human reads. Never parse machine-readable output from this. */
    output: string;
    /**
     * stdout alone. A tool's machine-readable surface (`--showConfig` JSON,
     * `--print-config` JSON) must be read from here: a child process is free to
     * print an unrelated warning to stderr, and the merged `output` would then
     * no longer parse.
     */
    stdout: string;
    stderr: string;
    durationMs: number;
    timedOut: boolean;
}

/** Trimmed stdout with stderr appended on its own line, the way every probe reports command output. */
function joinCommandOutput(stdout: string | undefined, stderr: string | undefined): string {
    return `${stdout ?? ''}${stderr ? `\n${stderr}` : ''}`.trim();
}

export async function runCommand(command: string, args: string[], cwd: string): Promise<CommandResult> {
    const startedAt = Date.now();

    try {
        const { stdout, stderr } = await execFileAsync(command, args, {
            cwd,
            timeout: PROCESS_TIMEOUT_MS,
            maxBuffer: MAX_BUFFER,
        });

        return {
            status: 0,
            output: joinCommandOutput(stdout, stderr),
            stdout: stdout ?? '',
            stderr: stderr ?? '',
            durationMs: Date.now() - startedAt,
            timedOut: false,
        };
    } catch (error) {
        const failure = error as NodeJS.ErrnoException & {
            stdout?: string;
            stderr?: string;
            code?: number | string;
            killed?: boolean;
        };

        return {
            status: typeof failure.code === 'number' ? failure.code : 1,
            output: joinCommandOutput(failure.stdout, failure.stderr) || failure.message,
            stdout: failure.stdout ?? '',
            stderr: failure.stderr ?? '',
            durationMs: Date.now() - startedAt,
            timedOut: failure.killed === true,
        };
    }
}
