/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel } from '../types';
import { withoutUndefined } from '../shared';
import { createRenameEventEslint } from './eslint';

const DEFAULT_FIX = 'auto';

export function renameEvent(config: {
    from: string;
    to: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    const {
        from,
        to,
        fix = DEFAULT_FIX,
        message,
    } = config;
    const usageConfig: DeprecationUsage = withoutUndefined({
        kind: 'rename-event',
        from,
        to,
        fix,
        message,
    });

    return {
        ...usageConfig,
        eslint: createRenameEventEslint(usageConfig),
    };
}
