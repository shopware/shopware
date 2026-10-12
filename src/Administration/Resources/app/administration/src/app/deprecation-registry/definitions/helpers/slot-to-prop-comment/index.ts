/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel } from '../types';
import { withoutUndefined } from '../shared';
import { createSlotToPropCommentEslint } from './eslint';

const DEFAULT_FIX: FixLevel = 'unsafe-auto';

/** Definition input of `slotToPropComment`: the slot whose content has to move to a prop by hand. */
export type SlotToPropCommentConfig = {
    slot: string;
    prop: string;
    fix?: FixLevel;
    message?: string;
};

export function slotToPropComment(config: SlotToPropCommentConfig): DeprecationUsage {
    const { slot, prop, fix = DEFAULT_FIX, message } = config;
    const usageConfig: DeprecationUsage & SlotToPropCommentConfig = withoutUndefined({
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
