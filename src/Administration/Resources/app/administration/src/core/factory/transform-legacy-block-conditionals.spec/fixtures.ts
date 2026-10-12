/**
 * @sw-package framework
 */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */
import { compile } from '@vue/compiler-dom';

export const TEST_COMPONENT = 'test-component';

/**
 * Serializes condition-helper options as the exact object literal expected in rewritten templates.
 * Use it in transform snapshots where the assertion compares generated template source.
 *
 * @example
 * options(0, true, 'defaultSlot');
 */
export function options(segmentCaseIndex: number, isStartingCondition: boolean, renderOrderSegment: string): string {
    return `{ segmentCaseIndex: ${segmentCaseIndex}, isStartingCondition: ${isStartingCondition}, renderOrderSegment: '${renderOrderSegment}' }`;
}

/**
 * Asserts that a rewritten template is still accepted by Vue's compiler.
 * Use it after transform assertions to catch invalid helper expression output.
 *
 * @example
 * expectTemplateCompiles('<div v-if="active"></div>');
 */
export function expectTemplateCompiles(template: string): void {
    expect(() => compile(template)).not.toThrow();
}
