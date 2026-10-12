/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import { normalizeFixLevel, withoutUndefined } from '../shared';
import { createRenamePropEslint } from './eslint';

const DEFAULT_FIX = 'auto';

export function renameProp(config: Record<string, unknown>): DeprecationUsage {
    const usageConfig: DeprecationUsage = withoutUndefined({
        kind: 'rename-prop',
        ...config,
        fix: normalizeFixLevel(config.fix, DEFAULT_FIX),
    });

    return {
        ...usageConfig,
        eslint: createRenamePropEslint(usageConfig),
    };
}
