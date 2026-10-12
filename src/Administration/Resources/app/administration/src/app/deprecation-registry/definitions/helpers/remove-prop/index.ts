/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel, MigrationTransform } from '../types';
import { withoutUndefined } from '../shared';
import { createRemovePropEslint } from './eslint';

const DEFAULT_FIX: FixLevel = 'auto';

/** Definition input of `removeProp`: the prop that is removed without replacement. */
export type RemovePropConfig = {
    prop: string;
    fix?: FixLevel;
    message?: string;
    transform?: MigrationTransform;
};

export function removeProp(config: RemovePropConfig): DeprecationUsage {
    const { prop, fix = DEFAULT_FIX, message, transform } = config;
    const usageConfig: DeprecationUsage & RemovePropConfig = withoutUndefined({
        kind: 'remove-prop',
        prop,
        fix,
        message,
        transform,
    });

    return {
        ...usageConfig,
        eslint: createRemovePropEslint(usageConfig),
    };
}
