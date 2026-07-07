import type { ContentSystemStyleOptionSpecification } from 'src/core/service/api/content-system-style-option.api.service';
import type { ContentElementNode } from '../types/content-element.types';
import { normalizeElementStyleForWrite } from './style-settings.util';

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
export function sanitizeContentElementForWrite(
    element: ContentElementNode,
    styleOptions?: Record<string, ContentSystemStyleOptionSpecification>,
): ContentElementNode {
    const sanitized: ContentElementNode = {
        id: element.id,
        component: element.component,
    };

    copyWritableContentElementFields(
        element,
        sanitized,
        (slotElements) => slotElements.map((slotElement) => sanitizeContentElementForWrite(slotElement, styleOptions)),
        styleOptions,
    );

    return sanitized;
}

/**
 * @private
 * @sw-package discovery
 */
export function sanitizeContentElementLayoutForWrite(
    layout: ContentElementNode[],
    styleOptions?: Record<string, ContentSystemStyleOptionSpecification>,
): ContentElementNode[] {
    return layout.map((element) => sanitizeContentElementForWrite(element, styleOptions));
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

    for (const [key, value] of Object.entries(style)) {
        if (value === null || value === undefined) {
            delete element.style[key];
        }
    }

    if (Object.keys(element.style).length === 0) {
        delete element.style;
    }

    return true;
}

function copyWritableContentElementFields(
    source: ContentElementNode,
    target: ContentElementNode,
    mapSlotElements: (slotElements: ContentElementNode[]) => ContentElementNode[],
    styleOptions?: Record<string, ContentSystemStyleOptionSpecification>,
): void {
    if (source.properties !== undefined) {
        target.properties = cloneDeep(source.properties);
    }

    if (source.style !== undefined) {
        const style = cloneDeep(source.style);
        const normalizedStyle = styleOptions
            ? normalizeElementStyleForWrite(style, styleOptions)
            : style;

        if (normalizedStyle !== undefined) {
            target.style = normalizedStyle;
        }
    }

    if (source.dataRequirements !== undefined) {
        target.dataRequirements = cloneDeep(source.dataRequirements);
    }

    if (source.providesContext !== undefined) {
        target.providesContext = cloneDeep(source.providesContext);
    }

    if (source.acceptsContext !== undefined) {
        target.acceptsContext = cloneDeep(source.acceptsContext);
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
