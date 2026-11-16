/**
 * @sw-package framework
 */

import type { FlagUsageStep, RequireTruePropStep, StepConfig } from '../types';
import { defineUsageStep, reportUsage } from '../shared';

/**
 * Reports every element of the component for a change only a developer can make.
 *
 * @example
 * flagUsage({ message: 'sw-help-sidebar is removed without replacement.' });
 */
export function flagUsage({ fix = 'manual', ...config }: StepConfig<FlagUsageStep>): FlagUsageStep {
    return defineUsageStep<FlagUsageStep>({ kind: 'flag-usage', fix, ...config }, (api) => {
        reportUsage(api, { node: api.node, apiName: api.node.name as string });
    });
}

/**
 * Keeps an element unconverted unless it sets a boolean prop to true, for a replacement that only behaves the same
 * in that mode. Put it into `onConvert`.
 *
 * @example
 * requireTrueProp({ prop: 'local-mode', message: 'sw-entity-multi-select does not save the association itself.' });
 * // <sw-entity-many-to-many-select :entity-collection="products" /> → not converted, manual
 */
export function requireTrueProp({ fix = 'manual', ...config }: StepConfig<RequireTruePropStep>): RequireTruePropStep {
    return defineUsageStep<RequireTruePropStep>({ kind: 'require-true-prop', fix, ...config }, (api, step) => {
        // Once converted, the conversion may have removed the prop, so only the old element tells
        if (api.node.name !== api.migration.component) {
            return;
        }

        const attribute = api.ast.findMatchingPropAttribute(api.node, step.prop);
        const isTrue =
            attribute &&
            (api.ast.getDirectiveName(attribute) !== 'bind' || api.ast.getAttributeValueSource(attribute) === 'true');

        if (!isTrue) {
            reportUsage(api, { node: api.node, apiName: step.prop, blocksConversion: true });
        }
    });
}
