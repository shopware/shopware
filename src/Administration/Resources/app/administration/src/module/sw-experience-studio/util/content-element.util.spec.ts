import type { ContentElementNode } from 'src/core/service/content-element.types';
import {
    findElementLocation,
    updateElementPropertiesInLayout,
    updateElementStyleInLayout,
    walkContentElements,
} from './content-element.util';

const { cloneDeep } = Shopware.Utils.object;

const ANCHOR_LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
const GERMAN_LANGUAGE_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

describe('walking content elements', () => {
    const children: ContentElementNode[] = [
        {
            id: 'child',
            component: 'Plugin:Container',
            slots: { content: [{ id: 'grandchild', component: 'content:text' }] },
        },
        { id: 'sibling', component: 'content:text' },
    ];
    const aside: ContentElementNode[] = [{ id: 'aside', component: 'content:text' }];
    const layout: ContentElementNode[] = [
        {
            id: 'root',
            component: 'Plugin:Container',
            slots: { empty: [], content: children, aside },
        },
        { id: 'next-root', component: 'content:text' },
    ];

    it('visits roots and nested slots in order with their original locations', () => {
        const original = cloneDeep(layout);
        const visited: Array<{ element: ContentElementNode; elements: ContentElementNode[]; index: number }> = [];

        const completed = walkContentElements(layout, (element, elements, index) => {
            visited.push({ element, elements, index });
        });

        expect(completed).toBe(true);
        expect(visited.map(({ element }) => element.id)).toEqual([
            'root',
            'child',
            'grandchild',
            'sibling',
            'aside',
            'next-root',
        ]);
        expect(visited[0].elements).toBe(layout);
        expect(visited[1].elements).toBe(children);
        expect(visited[2].elements).toBe(children[0].slots!.content);
        expect(visited[4].elements).toBe(aside);
        for (const { element, elements, index } of visited) {
            expect(elements[index]).toBe(element);
        }
        expect(layout).toEqual(original);
    });

    it('stops the whole walk when a visitor returns false inside a nested slot', () => {
        const visited: string[] = [];

        const completed = walkContentElements(layout, (element) => {
            visited.push(element.id);
            if (element.id === 'grandchild') {
                return false;
            }
        });

        expect(completed).toBe(false);
        expect(visited).toEqual(['root', 'child', 'grandchild']);
    });

    it('completes an empty layout without invoking the visitor', () => {
        const visit = jest.fn();

        expect(walkContentElements([], visit)).toBe(true);
        expect(visit).not.toHaveBeenCalled();
    });

    it('finds deeply nested elements in their original array', () => {
        const location = findElementLocation(layout, 'grandchild');

        expect(location?.elements).toBe(children[0].slots!.content);
        expect(location?.index).toBe(0);
    });

    it('returns null when no element matches across all roots and slots', () => {
        expect(findElementLocation(layout, 'missing')).toBeNull();
    });
});

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
                        text: {
                            [ANCHOR_LANGUAGE_ID]: 'Hello',
                        },
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
            text: {
                [ANCHOR_LANGUAGE_ID]: 'Updated text',
            },
            visibility: 'public',
        });

        expect(updated).toBe(true);
        expect(testLayout[0].slots!.content[0].properties).toEqual({
            text: {
                [ANCHOR_LANGUAGE_ID]: 'Updated text',
            },
            visibility: 'public',
        });
    });

    it('replaces a language map wholesale instead of merging its entries', () => {
        const testLayout = cloneDeep(layout);
        testLayout[0].slots!.content[0].properties = {
            text: {
                [ANCHOR_LANGUAGE_ID]: 'Hello',
                [GERMAN_LANGUAGE_ID]: 'Hallo',
            },
        };

        updateElementPropertiesInLayout(testLayout, 'child-1', {
            text: {
                [ANCHOR_LANGUAGE_ID]: 'Hello again',
            },
        });

        expect(testLayout[0].slots!.content[0].properties).toEqual({
            text: {
                [ANCHOR_LANGUAGE_ID]: 'Hello again',
            },
        });
    });

    it('returns false when updating properties for a missing element', () => {
        const testLayout = cloneDeep(layout);
        const updated = updateElementPropertiesInLayout(testLayout, 'missing', {
            text: {
                [ANCHOR_LANGUAGE_ID]: 'Updated text',
            },
        });

        expect(updated).toBe(false);
        expect(testLayout[0].slots!.content[0].properties).toEqual({
            text: {
                [ANCHOR_LANGUAGE_ID]: 'Hello',
            },
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
});
