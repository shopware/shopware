/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel } from '../types';
import { withoutUndefined } from '../shared';
import { createRenameVModelArgumentEslint } from './eslint';

const DEFAULT_FIX: FixLevel = 'auto';

/** Definition input of `renameVModelArgument`: the deprecated v-model argument and its replacement. */
export type RenameVModelArgumentConfig = {
    /** The deprecated v-model argument; `null` matches a plain `v-model`. */
    from: string | null;
    /** The replacement v-model argument; `null` turns it into a plain `v-model`. */
    to?: string | null;
    fix?: FixLevel;
    message?: string;
};

export function renameVModelArgument(config: RenameVModelArgumentConfig): DeprecationUsage {
    const { from, to = null, fix = DEFAULT_FIX, message } = config;
    const usageConfig: DeprecationUsage & RenameVModelArgumentConfig = withoutUndefined({
        kind: 'rename-v-model-argument',
        from,
        to,
        fix,
        message,
    });

    return {
        ...usageConfig,
        eslint: createRenameVModelArgumentEslint(usageConfig),
    };
}
