/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel } from '../types';
import { withoutUndefined } from '../shared';
import { createMapOptionsPropKeysEslint } from './eslint';

const DEFAULT_FIX = 'unsafe-auto';

export function mapOptionsPropKeys(config: {
    prop: string;
    from: Record<string, string>;
    fix?: FixLevel;
    message?: string;
    unsafeMessage?: string;
}): DeprecationUsage {
    const {
        prop,
        from,
        fix = DEFAULT_FIX,
        message,
        unsafeMessage,
    } = config;
    const usageConfig: DeprecationUsage = withoutUndefined({
        kind: 'map-options-prop-keys',
        prop,
        from,
        fix,
        message,
        unsafeMessage,
    });

    return {
        ...usageConfig,
        eslint: createMapOptionsPropKeysEslint(usageConfig),
    };
}
