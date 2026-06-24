/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel } from '../types';
import { withoutUndefined } from '../shared';
import { createMissingPropEslint } from './eslint';

const DEFAULT_FIX = 'auto';

export function missingProp(config: {
    prop: string;
    value: string;
    bind?: boolean;
    insertPosition?: 'after-name' | 'before-end';
    unlessProps?: string[];
    fix?: FixLevel;
    message?: string;
}): DeprecationUsage {
    const {
        prop,
        value,
        bind = false,
        insertPosition = 'before-end',
        unlessProps = [prop],
        fix = DEFAULT_FIX,
        message,
    } = config;
    const usageConfig: DeprecationUsage = withoutUndefined({
        kind: 'missing-prop',
        prop,
        value,
        bind,
        insertPosition,
        unlessProps,
        fix,
        message,
    });

    return {
        ...usageConfig,
        eslint: createMissingPropEslint(usageConfig),
    };
}
