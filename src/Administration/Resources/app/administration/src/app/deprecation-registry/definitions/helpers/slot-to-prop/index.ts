/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import { normalizeFixLevel, withoutUndefined } from '../shared';
import { createSlotToPropEslint } from './eslint';

const DEFAULT_FIX = 'auto';

export function slotToProp(config: Record<string, unknown>): DeprecationUsage {
    const usageConfig: DeprecationUsage = withoutUndefined({
        kind: 'slot-to-prop',
        ...config,
        fix: normalizeFixLevel(config.fix, DEFAULT_FIX),
    });

    return {
        ...usageConfig,
        eslint: createSlotToPropEslint(usageConfig),
    };
}
