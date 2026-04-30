/**
 * @sw-package framework
 */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */

import type { CommandRequest } from './types';

export function buildAdminBuildRequest(projectRoot: string): CommandRequest {
    return {
        name: 'admin:build',
        phase: 'build',
        cwd: projectRoot,
        command: 'composer build:js:admin',
    };
}
