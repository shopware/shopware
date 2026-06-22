/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel } from '../types';
import { withoutUndefined } from '../shared';
import { createCustomUsageEslint } from './eslint';

const DEFAULT_FIX = 'manual';

export function customUsage(config: {
    name: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    const {
        name,
        fix = DEFAULT_FIX,
        message,
    } = config;
    const usageConfig: DeprecationUsage = withoutUndefined({
        kind: 'custom',
        name,
        fix,
        message,
    });

    return {
        ...usageConfig,
        eslint: createCustomUsageEslint(usageConfig),
    };
}
