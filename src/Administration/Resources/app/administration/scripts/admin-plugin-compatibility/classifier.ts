/**
 * @sw-package framework
 */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import type { CommandResult, FailureClass } from './types';

const COMMAND_NOT_FOUND_PATTERN = /(command not found|not found|ENOENT|executable file not found)/i;

export function classifyCommandFailure(command: CommandResult): FailureClass {
    if ((command.name === 'commercial:license-generator' || command.name === 'commercial:license-generator-preflight') && isCommandNotFound(command)) {
        return 'setup';
    }

    if (command.phase === 'build') {
        return 'build';
    }

    if (command.phase === 'runtime') {
        return 'runtime';
    }

    return command.phase;
}

export function isCommandNotFound(command: CommandResult): boolean {
    return command.exitCode === 127 || COMMAND_NOT_FOUND_PATTERN.test(`${command.stderr}\n${command.stdout}`);
}
