/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel } from '../types';
import { withoutUndefined } from '../shared';
import { createSlotToItemsPropEslint } from './eslint';

const DEFAULT_FIX = 'unsafe-auto';

export function slotToItemsProp(config: {
    slot?: string;
    prop: string;
    itemComponent: string;
    itemNameProp?: string;
    itemRouteProp?: string;
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    const {
        slot = 'default',
        prop,
        itemComponent,
        itemNameProp = 'name',
        itemRouteProp = 'route',
        fix = DEFAULT_FIX,
        message,
    } = config;
    const usageConfig: DeprecationUsage = withoutUndefined({
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
