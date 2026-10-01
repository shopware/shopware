import type { Result } from 'axe-core';

export type AccessibilityNode = Result['nodes'][number] & {
    elementId?: string;
};

export type AccessibilityViolation = Omit<Result, 'nodes'> & {
    nodes: AccessibilityNode[];
};
