/**
 * @sw-package framework
 */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */
/* eslint-disable listeners/no-missing-remove-event-listener, listeners/no-inline-function-event-listener */

import { spawn } from 'child_process';
import { redactSecrets } from './secrets';
import type { CommandRequest, CommandResult } from './types';

const SAFE_SHELL_ARG_PATTERN = /^[A-Za-z0-9_/:=.,@%+-]+$/;

export function quoteShellArg(value: string): string {
    if (SAFE_SHELL_ARG_PATTERN.test(value)) {
        return value;
    }

    return `'${value.replace(/'/g, `'\\''`)}'`;
}

export function buildShellCommand(command: string, args: string[] = []): string {
    return [
        command,
        ...args.map(quoteShellArg),
    ].join(' ');
}

export async function runCommand(request: CommandRequest): Promise<CommandResult> {
    const startedAt = new Date();
    const started = performance.now();

    return new Promise((resolve) => {
        let stdout = '';
        let stderr = '';

        const child = spawn(request.command, {
            cwd: request.cwd,
            shell: true,
            env: process.env,
        });

        child.stdout?.on('data', (chunk: Buffer) => {
            stdout += chunk.toString('utf8');
        });

        child.stderr?.on('data', (chunk: Buffer) => {
            stderr += chunk.toString('utf8');
        });

        child.on('error', (error) => {
            stderr += `${error.message}\n`;
        });

        child.on('close', (exitCode) => {
            resolve({
                ...request,
                stdout: redactSecrets(stdout),
                stderr: redactSecrets(stderr),
                exitCode,
                durationMs: Math.round(performance.now() - started),
                startedAt: startedAt.toISOString(),
            });
        });
    });
}
