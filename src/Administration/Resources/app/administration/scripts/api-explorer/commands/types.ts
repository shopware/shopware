/**
 * @sw-package framework
 */

export interface CliIo {
    stdout: (text: string) => void;
    stderr: (text: string) => void;
}

export interface CliCommand {
    summary: string;
    usage: string;
    /** Resolves to the exit code. Throw `UsageError` for invalid arguments. */
    run: (args: string[], io: CliIo) => Promise<number>;
}

export class UsageError extends Error {}

export const EXIT_OK = 0;
export const EXIT_RUNTIME_ERROR = 1;
export const EXIT_USAGE_ERROR = 2;
