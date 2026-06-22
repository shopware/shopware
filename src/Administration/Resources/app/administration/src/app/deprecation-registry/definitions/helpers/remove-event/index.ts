/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel } from '../types';
import { withoutUndefined } from '../shared';
import { createRemoveEventEslint } from './eslint';

const DEFAULT_FIX = 'auto';

export function removeEvent(config: {
    event: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    const {
        event,
        fix = DEFAULT_FIX,
        message,
    } = config;
    const usageConfig: DeprecationUsage = withoutUndefined({
        kind: 'remove-event',
        event,
        fix,
        message,
    });

    return {
        ...usageConfig,
        eslint: createRemoveEventEslint(usageConfig),
    };
}
