/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel } from '../types';
import { withoutUndefined } from '../shared';
import { createRemoveSlotEslint } from './eslint';

const DEFAULT_FIX = 'auto';

export function removeSlot(config: {
    slot: string;
    fix?: FixLevel;
    message?: string;
    slotReviewHint?: string;
    slotReplacementComment?: string;
}): DeprecationUsage {
    const { slot, fix = DEFAULT_FIX, message, slotReviewHint, slotReplacementComment } = config;
    const usageConfig: DeprecationUsage = withoutUndefined({
        kind: 'remove-slot',
        slot,
        fix,
        message,
        slotReviewHint,
        slotReplacementComment,
    });

    return {
        ...usageConfig,
        eslint: createRemoveSlotEslint(usageConfig),
    };
}
