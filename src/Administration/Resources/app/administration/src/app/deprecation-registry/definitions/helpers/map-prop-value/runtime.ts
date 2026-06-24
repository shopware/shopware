/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import { propNameToAttributeName } from '../shared';

function hasOwn(object: Record<string, unknown>, key: string): boolean {
    return Object.prototype.hasOwnProperty.call(object, key);
}

export function createMapPropValueRuntime(usageConfig: DeprecationUsage): DeprecationUsage['runtime'] {
    return {
        detect: ({ usedProps }) => {
            const runtimeProp = usageConfig.runtimeProp;

            if (typeof runtimeProp !== 'string') {
                return false;
            }

            return [
                runtimeProp,
                propNameToAttributeName(runtimeProp),
            ].some((propName) => hasOwn(usedProps, propName) && usedProps[propName] === usageConfig.from);
        },
    };
}
