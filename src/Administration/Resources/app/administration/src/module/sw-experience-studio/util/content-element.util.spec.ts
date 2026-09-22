import type { ContentElementNode } from 'src/core/service/content-element.types';
import {
    findElementLocation,
    setElementMappingInLayout,
    updateElementPropertiesInLayout,
    updateElementStyleInLayout,
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

    it('updates nested element style in place', () => {
        const testLayout = cloneDeep(layout);

        const updated = updateElementStyleInLayout(testLayout, 'child-1', {
            'col-span': { md: 6 },
        });

        expect(updated).toBe(true);
        expect(testLayout[0].slots!.content[0].style).toEqual({
            'col-span': { md: 6 },
        });
    });

    it('removes style keys when updating with null', () => {
        const testLayout = cloneDeep(layout);
        testLayout[0].slots!.content[0].style = {
            'col-span': { lg: 6 },
        };

        updateElementStyleInLayout(testLayout, 'child-1', {
            'col-span': null,
        });

        expect(testLayout[0].slots!.content[0]).not.toHaveProperty('style');
    });

    it('maps a property onto entity data without discarding the authored value', () => {
        const testLayout = cloneDeep(layout);

        const updated = setElementMappingInLayout(testLayout, 'child-1', 'text', {
            path: 'category.name',
            contextType: 'single',
        });

        expect(updated).toBe(true);
        expect(testLayout[0].slots!.content[0].acceptsContext).toEqual({
            'category.name': {
                type: 'single',
                required: false,
                propertyAlias: 'text',
                scope: 'root',
            },
        });
        expect(testLayout[0].slots!.content[0].properties).toEqual({
            text: 'Hello',
        });
    });

    it('carries the candidate projection onto the stored consumer', () => {
        const testLayout = cloneDeep(layout);

        setElementMappingInLayout(testLayout, 'child-1', 'text', {
            path: 'product.cover',
            contextType: 'single',
            projection: 'product_media_to_media',
        });

        expect(testLayout[0].slots!.content[0].acceptsContext).toEqual({
            'product.cover': {
                type: 'single',
                required: false,
                propertyAlias: 'text',
                scope: 'root',
                projection: 'product_media_to_media',
            },
        });
    });

    // A present null is not an absent key to the server codec, which closes the consumer key set.
    it('omits the projection key entirely for a candidate that declares none', () => {
        const testLayout = cloneDeep(layout);

        setElementMappingInLayout(testLayout, 'child-1', 'text', {
            path: 'category.name',
            contextType: 'single',
            projection: null,
        });

        expect(testLayout[0].slots!.content[0].acceptsContext!['category.name']).not.toHaveProperty('projection');
    });

    it('replaces an existing mapping rather than accumulating consumers for one property', () => {
        const testLayout = cloneDeep(layout);

        setElementMappingInLayout(testLayout, 'child-1', 'text', {
            path: 'category.name',
            contextType: 'single',
        });
        setElementMappingInLayout(testLayout, 'child-1', 'text', {
            path: 'category.description',
            contextType: 'single',
        });

        expect(Object.keys(testLayout[0].slots!.content[0].acceptsContext!)).toEqual(['category.description']);
    });

    it('leaves consumers for other properties untouched when mapping one', () => {
        const testLayout = cloneDeep(layout);
        testLayout[0].slots!.content[0].acceptsContext = {
            'category.metaTitle': {
                type: 'single',
                required: false,
                propertyAlias: 'headline',
                scope: 'root',
            },
        };

        setElementMappingInLayout(testLayout, 'child-1', 'text', {
            path: 'category.name',
            contextType: 'single',
        });

        expect(Object.keys(testLayout[0].slots!.content[0].acceptsContext)).toEqual([
            'category.metaTitle',
            'category.name',
        ]);
    });

    it('drops the consumer map entirely when the last mapping is removed', () => {
        const testLayout = cloneDeep(layout);
        testLayout[0].slots!.content[0].acceptsContext = {
            'category.name': {
                type: 'single',
                required: false,
                propertyAlias: 'text',
                scope: 'root',
            },
        };

        const updated = setElementMappingInLayout(testLayout, 'child-1', 'text', null);

        expect(updated).toBe(true);
        expect(testLayout[0].slots!.content[0]).not.toHaveProperty('acceptsContext');
    });

    it('leaves mirrored reference wiring alone when unmapping the property it feeds', () => {
        const testLayout = cloneDeep(layout);
        const mirroredWiring = {
            productListing: {
                type: 'single' as const,
                required: true,
                propertyAlias: 'listing',
                scope: 'root' as const,
            },
        };
        testLayout[0].slots!.content[0].acceptsContext = mirroredWiring;

        setElementMappingInLayout(testLayout, 'child-1', 'listing', null);

        expect(testLayout[0].slots!.content[0].acceptsContext).toEqual(mirroredWiring);
    });

    it('returns false when mapping a property on a missing element', () => {
        const testLayout = cloneDeep(layout);

        expect(
            setElementMappingInLayout(testLayout, 'missing', 'text', {
                path: 'category.name',
                contextType: 'single',
            }),
        ).toBe(false);
    });
});
