/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel, MigrationTransform } from '../types';
import { withoutUndefined } from '../shared';
import { createRenamePropEslint } from './eslint';

const DEFAULT_FIX: FixLevel = 'auto';

/** Definition input of `renameProp`: the deprecated prop and its replacement. */
export type RenamePropConfig = {
    /** The deprecated prop name. */
    from: string;
    /** The replacement prop name. */
    to: string;
    fix?: FixLevel;
    message?: string;
    transform?: MigrationTransform;
};

export function renameProp(config: RenamePropConfig): DeprecationUsage {
    const { from, to, fix = DEFAULT_FIX, message, transform } = config;
    const usageConfig: DeprecationUsage & RenamePropConfig = withoutUndefined({
        kind: 'rename-prop',
        from,
        to,
        fix,
        message,
        transform,
    });

    return {
        ...usageConfig,
        eslint: createRenamePropEslint(usageConfig),
    };
}
