/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel } from '../types';
import { withoutUndefined } from '../shared';
import { createSlotToPropEslint } from './eslint';

const DEFAULT_FIX: FixLevel = 'auto';

/** Definition input of `slotToProp`: the deprecated slot and the prop that replaces it. */
export type SlotToPropConfig = {
    slot: string;
    prop: string;
    fix?: FixLevel;
    message?: string;
    slotReviewHint?: string;
};

export function slotToProp(config: SlotToPropConfig): DeprecationUsage {
    const { slot, prop, fix = DEFAULT_FIX, message, slotReviewHint } = config;
    const usageConfig: DeprecationUsage & SlotToPropConfig = withoutUndefined({
        kind: 'slot-to-prop',
        slot,
        prop,
        fix,
        message,
        slotReviewHint,
    });

    return {
        ...usageConfig,
        eslint: createSlotToPropEslint(usageConfig),
    };
}
