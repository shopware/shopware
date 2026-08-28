import type { ContentSystemPropertyResolution } from 'src/core/service/api/content-system-layout-draft-mutation.api.service';
import type { ContentElementNode } from 'src/core/service/content-element.types';

const { cloneDeep } = Shopware.Utils.object;

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * @param resolutions per-element property resolutions from the mutation response, keyed by element id
 *
 * @private
 * @sw-package discovery
 */
export function applyResolvedContextConsumers(
    layout: ContentElementNode[],
    resolutions: Record<string, ContentSystemPropertyResolution[]>,
): void {
    for (const [
        elementId,
        propertyResolutions,
    ] of Object.entries(resolutions)) {
        const location = findElementLocation(layout, elementId);
        const node = location?.elements[location.index];

        if (!node) {
            continue;
        }

        const accepts = isRecord(node.acceptsContext) ? node.acceptsContext : {};
        const dataRequirements = isRecord(node.dataRequirements) ? node.dataRequirements : {};
        const additions: Record<string, unknown> = {};

        for (const resolution of propertyResolutions) {
            const resolved = resolution.resolved;
            const contextKey = resolved?.contextKey;

            // Only parent-provided (context) resolutions become consumers; loader/stored fill themselves.
            if (!resolved || resolved.origin !== 'parent' || typeof contextKey !== 'string' || contextKey.length === 0) {
                continue;
            }

            // Already wired explicitly (authored consumer or data requirement): leave it.
            if (contextKey in accepts || contextKey in dataRequirements) {
                continue;
            }

            additions[contextKey] = {
                type: resolved.contextType ?? 'single',
                required: resolution.required,
            };
        }

        if (Object.keys(additions).length > 0) {
            node.acceptsContext = { ...accepts, ...additions };
        }
    }
}

/**
 * @private
 * @sw-package discovery
 */
export interface ElementLocation {
    elements: ContentElementNode[];
    index: number;
}

/**
 * @private
 * @sw-package discovery
 */
export function findElementLocation(layout: ContentElementNode[], elementId: string): ElementLocation | null {
    const rootIndex = layout.findIndex((element) => element.id === elementId);

    if (rootIndex !== -1) {
        return {
            elements: layout,
            index: rootIndex,
        };
    }

    for (const element of layout) {
        const location = findElementLocationInElement(element, elementId);

        if (location !== null) {
            return location;
        }
    }

    return null;
}

/**
 * @private
 * @sw-package discovery
 */
export function updateElementPropertiesInLayout(
    layout: ContentElementNode[],
    elementId: string,
    properties: Record<string, unknown>,
): boolean {
    const location = findElementLocation(layout, elementId);

    if (location === null) {
        return false;
    }

    const element = location.elements[location.index];

    if (!element) {
        return false;
    }

    element.properties = {
        ...(element.properties ?? {}),
        ...cloneDeep(properties),
    };

    return true;
}

/**
 * @private
 * @sw-package discovery
 */
export function updateElementStyleInLayout(
    layout: ContentElementNode[],
    elementId: string,
    style: Record<string, unknown>,
): boolean {
    const location = findElementLocation(layout, elementId);

    if (location === null) {
        return false;
    }

    const element = location.elements[location.index];

    if (!element) {
        return false;
    }

    element.style = {
        ...(element.style ?? {}),
        ...cloneDeep(style),
    };

    for (const [
        key,
        value,
    ] of Object.entries(style)) {
        if (value === null || value === undefined) {
            delete element.style[key];
        }
    }

    if (Object.keys(element.style).length === 0) {
        delete element.style;
    }

    return true;
}

function findElementLocationInElement(parent: ContentElementNode, elementId: string): ElementLocation | null {
    const slots = parent.slots ?? {};

    for (const slotElements of Object.values(slots)) {
        const index = slotElements.findIndex((element) => element.id === elementId);

        if (index !== -1) {
            return {
                elements: slotElements,
                index,
            };
        }

        for (const child of slotElements) {
            const location = findElementLocationInElement(child, elementId);

            if (location !== null) {
                return location;
            }
        }
    }

    return null;
}
