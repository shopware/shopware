import type { ContentSystemStyleOptionSpecification } from 'src/core/service/api/content-system-style-option.api.service';
import type { ContentSystemPropertyResolution } from 'src/core/service/api/content-system-layout-draft-mutation.api.service';
import type { ContentElementNode } from '../types/content-element.types';
import { normalizeElementStyleForWrite } from './style-settings.util';

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
    for (const [elementId, propertyResolutions] of Object.entries(resolutions)) {
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
 * Returns the `acceptsContext` an element type declares in its definition, or null when the type is unknown.
 *
 * @private
 * @sw-package discovery
 */
export type AcceptsContextResolver = (component: string) => Record<string, unknown> | null;

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

/**
 * Writes each element's declared context consumers into a layout tree.
 * Idempotent and per-key additive: a context key already carried by an authored consumer or a data
 * requirement on the node is left untouched, so explicit edits always win.
 *
 * @private
 * @sw-package discovery
 */
export function applyDeclaredContextConsumers(
    nodes: ContentElementNode[],
    resolveAcceptsContext: AcceptsContextResolver,
): void {
    const apply = (node: ContentElementNode): void => {
        const declared = resolveAcceptsContext(node.component);

        if (declared && Object.keys(declared).length > 0) {
            const accepts = isRecord(node.acceptsContext) ? node.acceptsContext : {};
            const dataRequirements = isRecord(node.dataRequirements) ? node.dataRequirements : {};
            const additions: Record<string, unknown> = {};

            for (const [
                contextKey,
                consumer,
            ] of Object.entries(declared)) {
                if (!(contextKey in accepts) && !(contextKey in dataRequirements)) {
                    additions[contextKey] = cloneDeep(consumer);
                }
            }

            if (Object.keys(additions).length > 0) {
                node.acceptsContext = { ...accepts, ...additions };
            }
        }

        const slots = node.slots ?? {};
        for (const slotName of Object.keys(slots)) {
            for (const child of slots[slotName]) {
                apply(child);
            }
        }
    };

    for (const node of nodes) {
        apply(node);
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
        const normalizedStyle = styleOptions ? normalizeElementStyleForWrite(style, styleOptions) : style;

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

        for (const [
            slotName,
            slotElements,
        ] of Object.entries(source.slots)) {
            target.slots[slotName] = mapSlotElements(slotElements);
        }
    }
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
