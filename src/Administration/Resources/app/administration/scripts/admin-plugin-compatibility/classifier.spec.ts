/**
 * @sw-package framework
 */

import { classifyCommandFailure } from './classifier';
import type { CommandResult } from './types';

describe('admin-plugin-compatibility failure classifier', () => {
    it('classifies plugin install and npm install failures as setup', () => {
        expect(classifyCommandFailure(createCommandResult('commercial:plugin-install', 'setup'))).toBe('setup');
        expect(classifyCommandFailure(createCommandResult('commercial:npm-install', 'setup'))).toBe('setup');
    });

    it('classifies a missing license generator as setup', () => {
        expect(classifyCommandFailure(createCommandResult(
            'commercial:license-generator',
            'license',
            127,
            'commercial-license-generator: command not found',
        ))).toBe('setup');
    });

    it('classifies a missing license generator preflight as setup', () => {
        expect(classifyCommandFailure(createCommandResult(
            'commercial:license-generator-preflight',
            'setup',
            127,
            'commercial-license-generator: command not found',
        ))).toBe('setup');
    });

    it('classifies license generator failures as license', () => {
        expect(classifyCommandFailure(createCommandResult('commercial:license-generator', 'license', 1, 'invalid host'))).toBe('license');
    });

    it('classifies Admin build failures as build', () => {
        expect(classifyCommandFailure(createCommandResult('admin:build', 'build', 1, 'Vite build failed'))).toBe('build');
    });

    it('classifies Playwright smoke failures as runtime', () => {
        expect(classifyCommandFailure(createCommandResult('admin:plugin-compatibility-smoke', 'runtime', 1))).toBe('runtime');
    });
});

function createCommandResult(
    name: CommandResult['name'],
    phase: CommandResult['phase'],
    exitCode = 1,
    stderr = '',
): CommandResult {
    return {
        name,
        phase,
        cwd: '/project',
        command: name,
        stdout: '',
        stderr,
        exitCode,
        durationMs: 1,
        startedAt: '2026-04-30T00:00:00.000Z',
    };
}
