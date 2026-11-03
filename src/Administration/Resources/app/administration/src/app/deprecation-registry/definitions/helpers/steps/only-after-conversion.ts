/**
 * @sw-package framework
 */

import type { ComponentUsageStep } from '../types';

/**
 * Limits a usage step to elements the codemod just converted from the deprecated component,
 * recognised by the "Converted from <component>" comment the conversion puts above them. Use it for
 * steps that carry the old component's behaviour over, so correct usage of the replacement stays unreported.
 *
 * @example
 * onlyAfterConversion(missingProp({ prop: 'size', value: '24px' }))
 */
export function onlyAfterConversion<Step extends ComponentUsageStep>(step: Step): Step {
    return { ...step, onlyAfterConversion: true };
}
