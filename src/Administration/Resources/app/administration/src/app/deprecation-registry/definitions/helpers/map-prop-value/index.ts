/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import {
    attributeNameToPropName,
    normalizeFixLevel,
    withoutUndefined,
} from '../shared';
import { createMapPropValueEslint } from './eslint';
import { createMapPropValueRuntime } from './runtime';

const DEFAULT_FIX = 'auto';

export function mapPropValue(config: Record<string, unknown>): DeprecationUsage {
    const runtimeProp = typeof config.runtimeProp === 'string'
        ? config.runtimeProp
        : typeof config.prop === 'string' ? attributeNameToPropName(config.prop) : undefined;
    const usageConfig: DeprecationUsage = withoutUndefined({
        kind: 'map-prop-value',
        ...config,
        runtimeProp,
        fix: normalizeFixLevel(config.fix, DEFAULT_FIX),
    });

    return {
        ...usageConfig,
        runtime: createMapPropValueRuntime(usageConfig),
        eslint: createMapPropValueEslint(usageConfig),
    };
}
