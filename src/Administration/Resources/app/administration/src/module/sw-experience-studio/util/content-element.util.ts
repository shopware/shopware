import type { ContentElementNode } from 'src/core/service/content-element.types';
import { isMappingConsumer } from './element-mapping.util';

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

/**
 * Points a mappable property at entity data, or returns it to its authored value when `mapping` is null.
 *
 * The authored value in `properties` is deliberately left untouched: the server treats a mapping as a higher
 * tier that shadows it, so unmapping restores whatever the author last typed.
 *
 * @private
 * @sw-package discovery
 */
export function setElementMappingInLayout(
    layout: ContentElementNode[],
    elementId: string,
    propertyKey: string,
    mapping: { path: string; contextType: 'single' | 'collection' } | null,
): boolean {
    const location = findElementLocation(layout, elementId);

    if (location === null) {
        return false;
    }

    const element = location.elements[location.index];

    if (!element) {
        return false;
    }

    const consumers = { ...(element.acceptsContext ?? {}) };

    for (const [
        path,
        consumer,
    ] of Object.entries(consumers)) {
        if (isMappingConsumer(path, consumer) && consumer.propertyAlias === propertyKey) {
            delete consumers[path];
        }
    }

    if (mapping !== null) {
        consumers[mapping.path] = {
            type: mapping.contextType,
            required: false,
            propertyAlias: propertyKey,
            scope: 'root',
        };
    }

    if (Object.keys(consumers).length === 0) {
        delete element.acceptsContext;

        return true;
    }

    element.acceptsContext = consumers;

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
