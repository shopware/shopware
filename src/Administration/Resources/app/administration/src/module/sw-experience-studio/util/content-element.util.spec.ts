import type { ContentElementNode } from '../types/content-element.types';
import {
    cloneContentElementWithNewIds,
    duplicateElementInLayout,
    findElementLocation,
    removeElementFromLayout,
    sanitizeContentElementLayoutForWrite,
    updateElementPropertiesInLayout,
} from './content-element.util';

const { cloneDeep } = Shopware.Utils.object;

describe('module/sw-experience-studio/util/content-element.util', () => {
    const rootElement: ContentElementNode = {
        id: 'root-1',
        component: 'layout:section',
        properties: {
            name: 'Section',
        },
        slots: {
            content: [
                {
                    id: 'child-1',
                    component: 'content:text',
                    properties: {
                        text: 'Hello',
                    },
                },
                {
                    id: 'child-2',
                    component: 'content:image',
                    properties: {
                        mediaId: 'media-1',
                    },
                },
            ],
        },
    };

    const layout: ContentElementNode[] = [
        rootElement,
        {
            id: 'root-2',
            component: 'layout:section',
            properties: {
                name: 'Footer',
            },
        },
    ];

    it('finds root element locations', () => {
        expect(findElementLocation(layout, 'root-2')).toEqual({
            elements: layout,
            index: 1,
        });
    });

    it('finds nested element locations', () => {
        expect(findElementLocation(layout, 'child-2')).toEqual({
            elements: rootElement.slots!.content,
            index: 1,
        });
    });

    it('clones an element with new ids recursively', () => {
        const duplicate = cloneContentElementWithNewIds(rootElement);

        expect(duplicate.id).not.toBe(rootElement.id);
        expect(duplicate.component).toBe(rootElement.component);
        expect(duplicate.properties).toEqual(rootElement.properties);
        expect(duplicate.slots!.content).toHaveLength(2);
        expect(duplicate.slots!.content[0].id).not.toBe('child-1');
        expect(duplicate.slots!.content[0].properties).toEqual({
            text: 'Hello',
        });
        expect(duplicate.slots!.content[1].id).not.toBe('child-2');
    });

    it('does not copy api-only fields such as extensions', () => {
        const elementWithApiFields = cloneDeep(rootElement);

        Object.assign(elementWithApiFields, {
            extensions: {
                foo: 'bar',
            },
        });
        Object.assign(elementWithApiFields.slots!.content[0], {
            extensions: {},
        });

        const duplicate = cloneContentElementWithNewIds(elementWithApiFields);

        expect(duplicate).not.toHaveProperty('extensions');
        expect(duplicate.slots!.content[0]).not.toHaveProperty('extensions');
    });

    it('sanitizes api-only fields from an entire layout tree', () => {
        const layoutWithApiFields = cloneDeep(layout);

        Object.assign(layoutWithApiFields[0].slots!.content[0], {
            extensions: {},
        });

        const sanitizedLayout = sanitizeContentElementLayoutForWrite(layoutWithApiFields);

        expect(sanitizedLayout[0].slots!.content[0]).not.toHaveProperty('extensions');
        expect(sanitizedLayout[0].slots!.content[0].id).toBe('child-1');
        expect(sanitizedLayout[0].slots!.content[0].properties).toEqual({
            text: 'Hello',
        });
    });

    it('duplicates an element directly after the source element', () => {
        const testLayout = cloneDeep(layout);

        const result = duplicateElementInLayout(testLayout, 'child-1');

        expect(result).not.toBeNull();
        expect(typeof result?.duplicatedId).toBe('string');
        expect(testLayout[0].slots!.content).toHaveLength(3);
        expect(testLayout[0].slots!.content[0].id).toBe('child-1');
        expect(testLayout[0].slots!.content[1].id).toBe(result?.duplicatedId);
        expect(testLayout[0].slots!.content[1].component).toBe('content:text');
        expect(testLayout[0].slots!.content[1].properties).toEqual({
            text: 'Hello',
        });
        expect(testLayout[0].slots!.content[2].id).toBe('child-2');
    });

    it('duplicates root elements at layout level', () => {
        const testLayout = cloneDeep(layout);

        const result = duplicateElementInLayout(testLayout, 'root-1');

        expect(result).not.toBeNull();
        expect(typeof result?.duplicatedId).toBe('string');
        expect(testLayout).toHaveLength(3);
        expect(testLayout[0].id).toBe('root-1');
        expect(testLayout[1].id).toBe(result?.duplicatedId);
        expect(testLayout[1].properties).toEqual({
            name: 'Section',
        });
        expect(testLayout[2].id).toBe('root-2');
    });

    it('returns null when the element does not exist', () => {
        expect(duplicateElementInLayout(layout, 'missing')).toBeNull();
    });

    it('removes a nested element from the layout', () => {
        const testLayout = cloneDeep(layout);

        expect(removeElementFromLayout(testLayout, 'child-1')).toBe(true);
        expect(testLayout[0].slots!.content).toHaveLength(1);
        expect(testLayout[0].slots!.content[0].id).toBe('child-2');
    });

    it('removes a root element from the layout', () => {
        const testLayout = cloneDeep(layout);

        expect(removeElementFromLayout(testLayout, 'root-1')).toBe(true);
        expect(testLayout).toHaveLength(1);
        expect(testLayout[0].id).toBe('root-2');
    });

    it('returns false when removing a non-existent element', () => {
        const testLayout = cloneDeep(layout);

        expect(removeElementFromLayout(testLayout, 'missing')).toBe(false);
        expect(testLayout).toHaveLength(2);
    });

    it('updates nested element properties in place', () => {
        const testLayout = cloneDeep(layout);

        const updated = updateElementPropertiesInLayout(testLayout, 'child-1', {
            text: 'Updated text',
            visibility: 'public',
        });

        expect(updated).toBe(true);
        expect(testLayout[0].slots!.content[0].properties).toEqual({
            text: 'Updated text',
            visibility: 'public',
        });
    });

    it('returns false when updating properties for a missing element', () => {
        const testLayout = cloneDeep(layout);
        const updated = updateElementPropertiesInLayout(testLayout, 'missing', {
            text: 'Updated text',
        });

        expect(updated).toBe(false);
        expect(testLayout[0].slots!.content[0].properties).toEqual({
            text: 'Hello',
        });
    });
});
