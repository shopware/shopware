/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel } from '../types';
import { withoutUndefined } from '../shared';
import type { MapOptionsPropKeysUsage } from './eslint';
import { createMapOptionsPropKeysEslint } from './eslint';

const DEFAULT_FIX: FixLevel = 'unsafe-auto';

/** Definition input of `mapOptionsPropKeys`: the options prop and the renamed keys of its option objects. */
export type MapOptionsPropKeysConfig = {
    /** The prop that receives the option objects. */
    prop: string;
    /** Maps each deprecated option object key to its replacement key. */
    from: Record<string, string>;
    /** The step is skipped when the element already maps keys through one of these props. */
    unlessProps?: string[];
    fix?: FixLevel;
    message?: string;
    /** Reported instead of `message` when the options expression cannot be rewritten automatically. */
    unsafeMessage?: string;
};

export function mapOptionsPropKeys(config: MapOptionsPropKeysConfig): DeprecationUsage {
    const { prop, from, unlessProps = [], fix = DEFAULT_FIX, message, unsafeMessage } = config;
    const usageConfig: MapOptionsPropKeysUsage = withoutUndefined({
        kind: 'map-options-prop-keys',
        prop,
        from,
        unlessProps,
        fix,
        message,
        unsafeMessage,
    });

    return {
        ...usageConfig,
        eslint: createMapOptionsPropKeysEslint(usageConfig),
    };
}
