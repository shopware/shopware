/**
 * @sw-package framework
 */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import path from 'path';
import { buildShellCommand } from './command';
import type { CommandRequest } from './types';

export const PLAYWRIGHT_CONFIG = 'playwright.admin-plugin-compatibility.config.ts';

export function buildSmokeTestRequest(projectRoot: string, smokeTags: string[] = []): CommandRequest {
    const args = [
        'playwright',
        'test',
        '--config',
        PLAYWRIGHT_CONFIG,
    ];

    if (smokeTags.length > 0) {
        args.push('--grep', smokeTags.join('|'));
    }

    return {
        name: 'admin:plugin-compatibility-smoke',
        phase: 'runtime',
        cwd: path.join(projectRoot, 'tests/acceptance'),
        command: buildShellCommand('npx', args),
    };
}
