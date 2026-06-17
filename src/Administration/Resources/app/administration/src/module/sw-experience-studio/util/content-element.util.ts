import type { ContentElementNode } from '../types/content-element.types';

const { cloneDeep } = Shopware.Utils.object;
const { createId } = Shopware.Utils;

type ContentElementWithAliases = ContentElementNode & {
    dataRequirements?: unknown;
    providesContext?: unknown;
    acceptsContext?: unknown;
};

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
export function findElementLocation(
    layout: ContentElementNode[],
    elementId: string,
): ElementLocation | null {
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
export function sanitizeContentElementForWrite(element: ContentElementNode): ContentElementNode {
    const sanitized: ContentElementNode = {
        id: element.id,
        component: element.component,
    };

    copyWritableContentElementFields(
        element,
        sanitized,
        (slotElements) => slotElements.map(sanitizeContentElementForWrite),
    );

    return sanitized;
}

/**
 * @private
 * @sw-package discovery
 */
export function sanitizeContentElementLayoutForWrite(layout: ContentElementNode[]): ContentElementNode[] {
    return layout.map(sanitizeContentElementForWrite);
}

/**
 * @private
 * @sw-package discovery
 */
export function cloneContentElementWithNewIds(element: ContentElementNode): ContentElementNode {
    const cloned: ContentElementNode = {
        id: createId(),
        component: element.component,
    };

    copyWritableContentElementFields(
        element,
        cloned,
        (slotElements) => slotElements.map(cloneContentElementWithNewIds),
    );

    return cloned;
}

/**
 * @private
 * @sw-package discovery
 */
export function duplicateElementInLayout(
    layout: ContentElementNode[],
    elementId: string,
): { duplicatedId: string } | null {
    const location = findElementLocation(layout, elementId);

    if (location === null) {
        return null;
    }

    const elementToDuplicate = location.elements[location.index];
    const duplicate = cloneContentElementWithNewIds(elementToDuplicate);

    location.elements.splice(location.index + 1, 0, duplicate);

    return {
        duplicatedId: duplicate.id,
    };
}

/**
 * @private
 * @sw-package discovery
 */
export function removeElementFromLayout(
    layout: ContentElementNode[],
    elementId: string,
): boolean {
    const location = findElementLocation(layout, elementId);

    if (location === null) {
        return false;
    }

    location.elements.splice(location.index, 1);

    return true;
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

function copyWritableContentElementFields(
    source: ContentElementNode,
    target: ContentElementNode,
    mapSlotElements: (slotElements: ContentElementNode[]) => ContentElementNode[],
): void {
    const sourceWithAliases = source as ContentElementWithAliases;

    if (source.properties !== undefined) {
        target.properties = cloneDeep(source.properties);
    }

    const dataRequirements = source.data_requirements ?? sourceWithAliases.dataRequirements;

    if (dataRequirements !== undefined) {
        target.data_requirements = cloneDeep(dataRequirements);
    }

    const providesContext = source.provides_context ?? sourceWithAliases.providesContext;

    if (providesContext !== undefined) {
        target.provides_context = cloneDeep(providesContext);
    }

    const acceptsContext = source.accepts_context ?? sourceWithAliases.acceptsContext;

    if (acceptsContext !== undefined) {
        target.accepts_context = cloneDeep(acceptsContext);
    }

    if (source.slots) {
        target.slots = {};

        for (const [slotName, slotElements] of Object.entries(source.slots)) {
            target.slots[slotName] = mapSlotElements(slotElements);
        }
    }
}

function findElementLocationInElement(
    parent: ContentElementNode,
    elementId: string,
): ElementLocation | null {
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
