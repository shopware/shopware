/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import { normalizeFixLevel, withoutUndefined } from '../shared';
import { createRemovePropEslint } from './eslint';

const DEFAULT_FIX = 'auto';

export function removeProp(config: Record<string, unknown>): DeprecationUsage {
    const usageConfig: DeprecationUsage = withoutUndefined({
        kind: 'remove-prop',
        ...config,
        fix: normalizeFixLevel(config.fix, DEFAULT_FIX),
    });

    return {
        ...usageConfig,
        eslint: createRemovePropEslint(usageConfig),
    };
}
