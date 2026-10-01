import type { AccessibilityViolation } from './accessibility.types';
import type { ContentSystemElementTypeSpecification } from 'src/core/service/api/content-system-element-type.api.service';

export type AccessibilityFixOperation = {
    type: 'set-property' | 'remove-property' | 'set-style' | 'remove-style';
    elementId: string;
    path: string;
    value?: unknown;
};

export type AccessibilityFixProposal = {
    summary: string;
    operations: AccessibilityFixOperation[];
};

export type AccessibilityFixRequest = {
    component: {
        id: string;
        component: string;
        properties?: Record<string, unknown>;
        style?: Record<string, unknown>;
    };
    violations: AccessibilityViolation[];
    elementSpecification?: Pick<ContentSystemElementTypeSpecification, 'properties'>;
};
