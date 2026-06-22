/**
 * @sw-package framework
 */
/* eslint-disable sw-deprecation-rules/private-feature-declarations */
import { compile } from '@vue/compiler-dom';

export const TEST_COMPONENT = 'test-component';

export function options(segmentCaseIndex: number, isStartingCondition: boolean, renderOrderSegment: string): string {
    return `{ segmentCaseIndex: ${segmentCaseIndex}, isStartingCondition: ${isStartingCondition}, renderOrderSegment: '${renderOrderSegment}' }`;
}

export function expectTemplateCompiles(template: string): void {
    expect(() => compile(template)).not.toThrow();
}
