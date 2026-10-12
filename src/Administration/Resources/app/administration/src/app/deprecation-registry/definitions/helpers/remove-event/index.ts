/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel } from '../types';
import { withoutUndefined } from '../shared';
import { createRemoveEventEslint } from './eslint';

const DEFAULT_FIX: FixLevel = 'auto';

/** Definition input of `removeEvent`: the event that is removed without replacement. */
export type RemoveEventConfig = {
    event: string;
    fix?: FixLevel;
    message?: string;
};

export function removeEvent(config: RemoveEventConfig): DeprecationUsage {
    const { event, fix = DEFAULT_FIX, message } = config;
    const usageConfig: DeprecationUsage & RemoveEventConfig = withoutUndefined({
        kind: 'remove-event',
        event,
        fix,
        message,
    });

    return {
        ...usageConfig,
        eslint: createRemoveEventEslint(usageConfig),
    };
}
