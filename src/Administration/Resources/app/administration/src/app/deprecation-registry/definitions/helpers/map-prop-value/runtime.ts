/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import { runtimePropWasUsed } from '../shared';

export function createMapPropValueRuntime(usageConfig: DeprecationUsage): DeprecationUsage['runtime'] {
    return {
        detect: ({ usedProps }) => runtimePropWasUsed(usageConfig.runtimeProp, usedProps),
    };
}
