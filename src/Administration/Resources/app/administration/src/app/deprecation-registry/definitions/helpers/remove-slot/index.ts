/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel } from '../types';
import { withoutUndefined } from '../shared';
import { createRemoveSlotEslint } from './eslint';

const DEFAULT_FIX: FixLevel = 'auto';

/** Definition input of `removeSlot`: the slot that is removed without replacement. */
export type RemoveSlotConfig = {
    slot: string;
    fix?: FixLevel;
    message?: string;
    slotReviewHint?: string;
    slotReplacementComment?: string;
};

export function removeSlot(config: RemoveSlotConfig): DeprecationUsage {
    const { slot, fix = DEFAULT_FIX, message, slotReviewHint, slotReplacementComment } = config;
    const usageConfig: DeprecationUsage & RemoveSlotConfig = withoutUndefined({
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
