/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel } from '../types';
import { withoutUndefined } from '../shared';
import type { SlotToItemsPropUsage } from './eslint';
import { createSlotToItemsPropEslint } from './eslint';

const DEFAULT_FIX: FixLevel = 'unsafe-auto';

/** Definition input of `slotToItemsProp`: the slot whose item elements become an items prop. */
export type SlotToItemsPropConfig = {
    /** The slot that holds the item elements; defaults to `default`. */
    slot?: string;
    /** The prop that receives the generated items array. */
    prop: string;
    /** The element name of each item inside the slot. */
    itemComponent: string;
    /** The item attribute read as the item name; defaults to `name`. */
    itemNameProp?: string;
    /** The item attribute read as the route when no name is set; defaults to `route`. */
    itemRouteProp?: string;
    fix?: FixLevel;
    message?: string;
};

export function slotToItemsProp(config: SlotToItemsPropConfig): DeprecationUsage {
    const {
        slot = 'default',
        prop,
        itemComponent,
        itemNameProp = 'name',
        itemRouteProp = 'route',
        fix = DEFAULT_FIX,
        message,
    } = config;
    const usageConfig: SlotToItemsPropUsage = withoutUndefined({
        kind: 'slot-to-items-prop',
        slot,
        prop,
        itemComponent,
        itemNameProp,
        itemRouteProp,
        fix,
        message,
    });

    return {
        ...usageConfig,
        eslint: createSlotToItemsPropEslint(usageConfig),
    };
}
