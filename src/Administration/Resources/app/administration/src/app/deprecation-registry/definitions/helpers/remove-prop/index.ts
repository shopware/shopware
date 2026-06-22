/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import {
    attributeNameToPropName,
    normalizeFixLevel,
    withoutUndefined,
} from '../shared';
import { createRemovePropEslint } from './eslint';
import { createRemovePropRuntime } from './runtime';

const DEFAULT_FIX = 'auto';

export function removeProp(config: Record<string, unknown>): DeprecationUsage {
    const runtimeProp = typeof config.runtimeProp === 'string'
        ? config.runtimeProp
        : typeof config.prop === 'string' ? attributeNameToPropName(config.prop) : undefined;
    const usageConfig: DeprecationUsage = withoutUndefined({
        kind: 'remove-prop',
        ...config,
        runtimeProp,
        fix: normalizeFixLevel(config.fix, DEFAULT_FIX),
    });

    return {
        ...usageConfig,
        runtime: createRemovePropRuntime(usageConfig),
        eslint: createRemovePropEslint(usageConfig),
    };
}
