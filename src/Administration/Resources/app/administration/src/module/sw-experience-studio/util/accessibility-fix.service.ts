import type { ContentElementNode } from 'src/core/service/content-element.types';
import type { ContentSystemElementTypeSpecification } from 'src/core/service/api/content-system-element-type.api.service';
import type { AccessibilityViolation } from './accessibility.types';
import type {
    AccessibilityFixOperation,
    AccessibilityFixProposal,
    AccessibilityFixRequest,
} from './accessibility-fix.types';

const ACCESSIBILITY_FIX_ENDPOINT = 'http://127.0.0.1:3466/v1/chat/completions';
const ACCESSIBILITY_FIX_MODEL = 'gpt-5.6-luna';

const SYSTEM_PROMPT = `You fix accessibility issues in a content component.
Return only valid JSON with this shape:
{"summary":"string","operations":[{"type":"set-property|remove-property|set-style|remove-style","elementId":"string","path":"topLevelPropertyOrNestedPath","value": any}]}
Only change the supplied component. Use only properties declared in elementSpecification.properties or style values. Use the exact declared property key without a properties. prefix. Never return HTML, JavaScript, CSS source code, or changes to another element.
Use remove operations without a value. Keep values JSON serializable. If no safe fix is possible, return an empty operations array and explain why in summary.`;

export class AccessibilityFixError extends Error {}

export async function requestAccessibilityFix(
    element: ContentElementNode,
    violations: AccessibilityViolation[],
    elementSpecification: Pick<ContentSystemElementTypeSpecification, 'properties'> | null,
): Promise<AccessibilityFixProposal> {
    const request: AccessibilityFixRequest = {
        component: {
            id: element.id,
            component: element.component,
            properties: element.properties,
            style: element.style,
        },
        violations,
        elementSpecification: elementSpecification
            ? { properties: elementSpecification.properties }
            : undefined,
    };

    const response = await fetch(ACCESSIBILITY_FIX_ENDPOINT, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            model: ACCESSIBILITY_FIX_MODEL,
            temperature: 0,
            messages: [
                {
                    role: 'system',
                    content: SYSTEM_PROMPT,
                },
                {
                    role: 'user',
                    content: JSON.stringify(request),
                },
            ],
        }),
    });

    if (!response.ok) {
        throw new AccessibilityFixError(`The accessibility fixer returned HTTP ${response.status}.`);
    }

    const responseBody = (await response.json()) as {
        choices?: Array<{
            message?: {
                content?: string | Array<{ text?: string }>;
            };
        }>;
    };
    const content = responseBody.choices?.[0]?.message?.content;

    if (!content) {
        throw new AccessibilityFixError('The accessibility fixer returned no proposal.');
    }

    const proposal = parseProposal(typeof content === 'string' ? content : content.map((part) => part.text ?? '').join(''));

    return validateProposal(proposal, element.id, elementSpecification);
}

function parseProposal(content: string): unknown {
    const normalizedContent = content.trim().replace(/^```(?:json)?\s*/i, '').replace(/\s*```$/, '');

    try {
        return JSON.parse(normalizedContent);
    } catch {
        throw new AccessibilityFixError('The accessibility fixer returned invalid JSON.');
    }
}

function validateProposal(
    value: unknown,
    elementId: string,
    elementSpecification: Pick<ContentSystemElementTypeSpecification, 'properties'> | null,
): AccessibilityFixProposal {
    if (!isRecord(value) || typeof value.summary !== 'string' || !Array.isArray(value.operations)) {
        throw new AccessibilityFixError('The accessibility fixer returned an invalid proposal.');
    }

    const operations = value.operations.map((operation) => validateOperation(operation, elementId, elementSpecification));

    return {
        summary: value.summary,
        operations,
    };
}

function validateOperation(
    value: unknown,
    elementId: string,
    elementSpecification: Pick<ContentSystemElementTypeSpecification, 'properties'> | null,
): AccessibilityFixOperation {
    if (!isRecord(value)) {
        throw new AccessibilityFixError('The accessibility fixer returned an invalid operation.');
    }

    const operationType = value.type;
    const path = normalizePath(value.path, operationType);

    if (
        (operationType !== 'set-property'
            && operationType !== 'remove-property'
            && operationType !== 'set-style'
            && operationType !== 'remove-style')
        || value.elementId !== elementId
        || typeof path !== 'string'
        || !/^[A-Za-z][A-Za-z0-9_-]*(\.[A-Za-z][A-Za-z0-9_-]*)*$/.test(path)
        || path.split('.').some((part) => part === '__proto__' || part === 'constructor' || part === 'prototype')
    ) {
        throw new AccessibilityFixError('The accessibility fixer proposed an unsupported operation.');
    }

    if (operationType === 'set-property' || operationType === 'remove-property') {
        const propertyPath = path.split('.');

        if (elementSpecification && !isDeclaredProperty(elementSpecification.properties, propertyPath)) {
            throw new AccessibilityFixError('The accessibility fixer proposed an undeclared component property.');
        }
    }

    const isSetOperation = operationType === 'set-property' || operationType === 'set-style';

    if (isSetOperation && !Object.prototype.hasOwnProperty.call(value, 'value')) {
        throw new AccessibilityFixError('The accessibility fixer proposed a set operation without a value.');
    }

    return {
        type: operationType,
        elementId,
        path,
        ...(isSetOperation ? { value: value.value } : {}),
    } as AccessibilityFixOperation;
}

function normalizePath(path: unknown, operationType: unknown): unknown {
    if (typeof path !== 'string') {
        return path;
    }

    if ((operationType === 'set-property' || operationType === 'remove-property') && path.startsWith('properties.')) {
        return path.slice('properties.'.length);
    }

    if ((operationType === 'set-style' || operationType === 'remove-style') && path.startsWith('style.')) {
        return path.slice('style.'.length);
    }

    return path;
}

function isDeclaredProperty(
    properties: ContentSystemElementTypeSpecification['properties'],
    path: string[],
): boolean {
    const property = properties[path[0] ?? ''];

    if (!property) {
        return false;
    }

    if (path.length === 1) {
        return true;
    }

    return Boolean(property.properties && isDeclaredProperty(property.properties, path.slice(1)));
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}
