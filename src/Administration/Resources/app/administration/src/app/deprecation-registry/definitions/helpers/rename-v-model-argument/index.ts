/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel } from '../types';
import { withoutUndefined } from '../shared';
import { createRenameVModelArgumentEslint } from './eslint';

const DEFAULT_FIX = 'auto';

export function renameVModelArgument(config: {
    from: string | null;
    to?: string | null;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    const {
        from,
        to = null,
        fix = DEFAULT_FIX,
        message,
    } = config;
    const usageConfig: DeprecationUsage = withoutUndefined({
        kind: 'rename-v-model-argument',
        from,
        to,
        fix,
        message,
    });

    return {
        ...usageConfig,
        eslint: createRenameVModelArgumentEslint(usageConfig),
    };
}
