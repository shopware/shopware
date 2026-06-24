/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import { attributeNameToPropName, normalizeFixLevel, withoutUndefined } from '../shared';
import { createRenamePropEslint } from './eslint';
import { createRenamePropRuntime } from './runtime';

const DEFAULT_FIX = 'auto';

export function renameProp(config: Record<string, unknown>): DeprecationUsage {
    const runtimeProp =
        typeof config.runtimeProp === 'string'
            ? config.runtimeProp
            : typeof config.from === 'string'
              ? attributeNameToPropName(config.from)
              : undefined;
    const usageConfig: DeprecationUsage = withoutUndefined({
        kind: 'rename-prop',
        ...config,
        runtimeProp,
        fix: normalizeFixLevel(config.fix, DEFAULT_FIX),
    });

    return {
        ...usageConfig,
        runtime: createRenamePropRuntime(usageConfig),
        eslint: createRenamePropEslint(usageConfig),
    };
}
