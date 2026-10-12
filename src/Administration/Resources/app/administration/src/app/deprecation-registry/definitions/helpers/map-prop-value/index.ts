/**
 * @sw-package framework
 */

import type { DeprecationUsage } from '../types';
import { normalizeFixLevel, withoutUndefined } from '../shared';
import { createMapPropValueEslint } from './eslint';

const DEFAULT_FIX = 'auto';

export function mapPropValue(config: Record<string, unknown>): DeprecationUsage {
    const usageConfig: DeprecationUsage = withoutUndefined({
        kind: 'map-prop-value',
        ...config,
        fix: normalizeFixLevel(config.fix, DEFAULT_FIX),
    });

    return {
        ...usageConfig,
        eslint: createMapPropValueEslint(usageConfig),
    };
}
