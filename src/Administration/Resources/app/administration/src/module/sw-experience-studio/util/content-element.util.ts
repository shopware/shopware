import type { ContentElementNode } from 'src/core/service/content-element.types';

const { cloneDeep } = Shopware.Utils.object;

/**
 * @private
 * @sw-package discovery
 */
export interface ElementLocation {
    elements: ContentElementNode[];
    index: number;
}

/**
 * Visits each element before its nested slots, preserving array and slot order.
 * Returning false from the visitor stops the entire walk and makes this function return false.
 *
 * @private
 * @sw-package discovery
 */
export function walkContentElements(
    elements: ContentElementNode[],
    visit: (element: ContentElementNode, elements: ContentElementNode[], index: number) => boolean | void,
): boolean {
    for (const [index, element] of elements.entries()) {
        if (visit(element, elements, index) === false) {
            return false;
        }

        for (const slotElements of Object.values(element.slots ?? {})) {
            if (!walkContentElements(slotElements, visit)) {
                return false;
            }
        }
    }

    return true;
}

/**
 * @private
 * @sw-package discovery
 */
export function findElementLocation(layout: ContentElementNode[], elementId: string): ElementLocation | null {
    let location: ElementLocation | null = null;

    walkContentElements(layout, (element, elements, index) => {
        if (element.id !== elementId) {
            return;
        }

        location = { elements, index };
        return false;
    });

    return location;
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
