/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel } from '../types';
import { withoutUndefined } from '../shared';
import { createRemoveSlotEslint } from './eslint';

const DEFAULT_FIX = 'auto';

export function removeSlot(config: { slot: string; fix?: FixLevel; message?: string }): DeprecationUsage {
    const { slot, fix = DEFAULT_FIX, message } = config;
    const usageConfig: DeprecationUsage = withoutUndefined({
        kind: 'remove-slot',
        slot,
        fix,
        message,
    });

    return {
        ...usageConfig,
        eslint: createRemoveSlotEslint(usageConfig),
    };
}
