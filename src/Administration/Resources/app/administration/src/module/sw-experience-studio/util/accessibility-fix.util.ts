import type { ContentElementNode } from 'src/core/service/content-element.types';
import { findElementLocation } from './content-element.util';
import type { AccessibilityFixOperation } from './accessibility-fix.types';

export function applyAccessibilityFixOperations(
    layout: ContentElementNode[],
    operations: AccessibilityFixOperation[],
): boolean {
    for (const operation of operations) {
        const location = findElementLocation(layout, operation.elementId);

        if (!location) {
            return false;
        }

        const element = location.elements[location.index];

        if (!element) {
            return false;
        }

        const targetKey = operation.type.includes('property') ? 'properties' : 'style';
        const existingTarget = element[targetKey];

        if (operation.type.startsWith('remove-') && !existingTarget) {
            continue;
        }

        const target = existingTarget ?? (element[targetKey] = {});
        const path = operation.path.split('.');
        const key = path.pop();

        if (!key) {
            return false;
        }

        let parent: Record<string, unknown> | null = target;

        for (const part of path) {
            if (!parent) {
                break;
            }

            const nestedValue = parent[part];

            if (typeof nestedValue !== 'object' || nestedValue === null || Array.isArray(nestedValue)) {
                if (operation.type.startsWith('remove-')) {
                    parent = null;
                    break;
                }

                parent[part] = {};
            }

            parent = parent[part] as Record<string, unknown>;
        }

        if (!parent) {
            continue;
        }

        if (operation.type.startsWith('remove-')) {
            delete parent[key];
        } else {
            parent[key] = operation.value;
        }
    }

    return true;
}
