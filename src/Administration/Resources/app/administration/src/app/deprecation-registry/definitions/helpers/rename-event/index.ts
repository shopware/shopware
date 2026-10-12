/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel } from '../types';
import { withoutUndefined } from '../shared';
import { createRenameEventEslint } from './eslint';

const DEFAULT_FIX: FixLevel = 'auto';

/** Definition input of `renameEvent`: the deprecated event and its replacement. */
export type RenameEventConfig = {
    /** The deprecated event name. */
    from: string;
    /** The replacement event name. */
    to: string;
    fix?: FixLevel;
    message?: string;
};

export function renameEvent(config: RenameEventConfig): DeprecationUsage {
    const { from, to, fix = DEFAULT_FIX, message } = config;
    const usageConfig: DeprecationUsage & RenameEventConfig = withoutUndefined({
        kind: 'rename-event',
        from,
        to,
        fix,
        message,
    });

    return {
        ...usageConfig,
        eslint: createRenameEventEslint(usageConfig),
    };
}
