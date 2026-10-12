/**
 * @sw-package framework
 */

import type { DeprecationUsage, FixLevel } from '../types';
import { withoutUndefined } from '../shared';
import type { MissingPropUsage } from './eslint';
import { createMissingPropEslint } from './eslint';

const DEFAULT_FIX: FixLevel = 'auto';

/** Definition input of `missingProp`: the prop a usage must set and the value the fix adds. */
export type MissingPropConfig = {
    prop: string;
    /** The value source the fix writes into the added attribute. */
    value: string;
    /** Adds the prop as a `:prop` binding instead of a static attribute. */
    bind?: boolean;
    insertPosition?: 'after-name' | 'before-end';
    /** The step is skipped when the element already sets one of these props; defaults to `[prop]`. */
    unlessProps?: string[];
    fix?: FixLevel;
    message?: string;
    /** Puts a codemod review comment above the element when the prop is added. */
    reviewHint?: string;
};

export function missingProp(config: MissingPropConfig): DeprecationUsage {
    const {
        prop,
        value,
        bind = false,
        insertPosition = 'before-end',
        unlessProps = [prop],
        fix = DEFAULT_FIX,
        message,
        reviewHint,
    } = config;
    const usageConfig: MissingPropUsage = withoutUndefined({
        kind: 'missing-prop',
        prop,
        value,
        bind,
        insertPosition,
        unlessProps,
        fix,
        message,
        reviewHint,
    });

    return {
        ...usageConfig,
        eslint: createMissingPropEslint(usageConfig),
    };
}
