import type { ContentSystemPropertyResolution } from 'src/core/service/api/content-system-layout-draft-mutation.api.service';
import type { ContentElementNode } from 'src/core/service/api/content-element.types';
import {
    applyResolvedContextConsumers,
    findElementLocation,
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

    describe('applyResolvedContextConsumers', () => {
        const parent = (
            key: string,
            contextKey: string,
            contextType: 'single' | 'collection',
            required: boolean,
        ): ContentSystemPropertyResolution => ({
            key,
            kind: 'reference',
            required,
            type: null,
            default: null,
            fqcn: 'App\\Entity',
            resolved: {
                origin: 'parent',
                contextKey,
                providerElementId: 'root',
                path: null,
                distribution: 'broadcast',
                contextType,
                loaderSource: null,
                configTemplate: null,
                configComplete: false,
            },
            candidates: [],
        });

        const nonParent = (
            key: string,
            resolved: ContentSystemPropertyResolution['resolved'],
        ): ContentSystemPropertyResolution => ({
            key,
            kind: 'reference',
            required: true,
            type: null,
            default: null,
            fqcn: 'App\\Entity',
            resolved,
            candidates: [],
        });

        it('writes a consumer for each parent-resolved property, using the resolved key, type and required', () => {
            const price: ContentElementNode = { id: 'p1', component: 'Sw:Product:PriceDisplay' };

            applyResolvedContextConsumers([price], {
                p1: [
                    parent('product', 'product', 'single', true),
                    parent('reviews', 'reviews', 'collection', false),
                ],
            });

            expect(price.acceptsContext).toEqual({
                product: { type: 'single', required: true },
                reviews: { type: 'collection', required: false },
            });
        });

        it('ignores loader, stored and unresolved properties', () => {
            const element: ContentElementNode = { id: 'e1', component: 'Sw:Test' };

            applyResolvedContextConsumers([element], {
                e1: [
                    nonParent('media', {
                        origin: 'loader',
                        contextKey: null,
                        providerElementId: null,
                        path: null,
                        distribution: null,
                        contextType: null,
                        loaderSource: 'entity',
                        configTemplate: { entity: 'media', property: 'mediaId' },
                        configComplete: true,
                    }),
                    nonParent('wired', {
                        origin: 'stored',
                        contextKey: null,
                        providerElementId: null,
                        path: null,
                        distribution: null,
                        contextType: null,
                        loaderSource: null,
                        configTemplate: null,
                        configComplete: null,
                    }),
                    nonParent('missing', null),
                ],
            });

            expect(element.acceptsContext).toBeUndefined();
        });

        it('never overrides authored wiring (consumer or data requirement)', () => {
            const authored = { product: { type: 'single', required: true, propertyAlias: 'item' } };
            const withConsumer: ContentElementNode = {
                id: 'e1',
                component: 'Sw:Test',
                acceptsContext: cloneDeep(authored),
            };
            const withRequirement: ContentElementNode = {
                id: 'e2',
                component: 'Sw:Test',
                dataRequirements: { product: { source: 'entity', config: { entityName: 'product', id: 'abc' } } },
            };

            applyResolvedContextConsumers(
                [
                    withConsumer,
                    withRequirement,
                ],
                {
                    e1: [parent('product', 'product', 'single', true)],
                    e2: [parent('product', 'product', 'single', true)],
                },
            );

            expect(withConsumer.acceptsContext).toEqual(authored);
            expect(withRequirement.acceptsContext).toBeUndefined();
        });

        it('resolves nested elements by id and ignores ids not in the layout', () => {
            const nested: ContentElementNode = { id: 'c1', component: 'Sw:Product:PriceDisplay' };
            const grid: ContentElementNode = { id: 'g1', component: 'Sw:Grid', slots: { default: [nested] } };

            applyResolvedContextConsumers([grid], {
                c1: [parent('product', 'product', 'single', true)],
                unknown: [parent('product', 'product', 'single', true)],
            });

            expect(nested.acceptsContext).toEqual({ product: { type: 'single', required: true } });
            expect(grid.acceptsContext).toBeUndefined();
        });
    });
});
