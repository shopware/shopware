/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel, MigrationTransform } from '../types';
import { withoutUndefined } from '../shared';
import { createMapPropValueEslint } from './eslint';

const DEFAULT_FIX: FixLevel = 'auto';

/** Definition input of `mapPropValue`: a static value of `prop` and the value that replaces it. */
export type MapPropValueConfig = {
    prop: string;
    from: string;
    /** Omitted for manual mappings without a replacement value. */
    to?: string;
    fix?: FixLevel;
    message?: string;
    transform?: MigrationTransform;
};

export function mapPropValue(config: MapPropValueConfig): DeprecationUsage {
    const { prop, from, to, fix = DEFAULT_FIX, message, transform } = config;
    const usageConfig: DeprecationUsage & MapPropValueConfig = withoutUndefined({
        kind: 'map-prop-value',
        prop,
        from,
        to,
        fix,
        message,
        transform,
    });

    return {
        ...usageConfig,
        eslint: createMapPropValueEslint(usageConfig),
    };
}
