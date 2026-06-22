/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import { runtimePropWasUsed } from '../shared';

export function createRemovePropRuntime(usageConfig: DeprecationUsage): DeprecationUsage['runtime'] {
    return {
        detect: ({ usedProps }) => runtimePropWasUsed(usageConfig.runtimeProp, usedProps),
    };
}
