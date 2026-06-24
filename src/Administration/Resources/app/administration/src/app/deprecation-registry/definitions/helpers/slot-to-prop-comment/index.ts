/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel } from '../types';
import { withoutUndefined } from '../shared';
import { createSlotToPropCommentEslint } from './eslint';

const DEFAULT_FIX = 'unsafe-auto';

export function slotToPropComment(config: {
    slot: string;
    prop: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    const { slot, prop, fix = DEFAULT_FIX, message } = config;
    const usageConfig: DeprecationUsage = withoutUndefined({
        kind: 'slot-to-prop-comment',
        slot,
        prop,
        fix,
        message,
    });

    return {
        ...usageConfig,
        eslint: createSlotToPropCommentEslint(usageConfig),
    };
}
