import type { ContentSystemPropertyResolution } from 'src/core/service/api/content-system-layout-draft-mutation.api.service';
import type { ContentSystemStyleOptionSpecification } from 'src/core/service/api/content-system-style-option.api.service';
import type { ContentElementNode } from '../types/content-element.types';
import {
    applyResolvedContextConsumers,
    findElementLocation,
    sanitizeContentElementLayoutForWrite,
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

    it('normalizes breakpoint-aware style scalars when sanitizing elements for write', () => {
        const layoutWithStyle = cloneDeep(layout);
        const paddingOption: ContentSystemStyleOptionSpecification = {
            type: 'string',
            enum: null,
            range: null,
            maxLength: 64,
            default: null,
            breakpointAware: true,
            adminUI: {
                component: 'box-spacing',
                label: 'Padding',
            },
        };

        layoutWithStyle[0].slots!.content[0].style = {
            padding: '20px 40px 20px 40px',
            'col-span': { lg: 6 },
        };

        const sanitizedLayout = sanitizeContentElementLayoutForWrite(layoutWithStyle, {
            padding: paddingOption,
            'col-span': {
                type: 'integer',
                enum: null,
                range: { min: 1, max: 12 },
                maxLength: null,
                default: null,
                breakpointAware: true,
                adminUI: {
                    component: 'number',
                    label: 'Column Span',
                },
            },
        });

        expect(sanitizedLayout[0].slots!.content[0].style).toEqual({
            padding: {
                xs: '20px 40px 20px 40px',
                sm: '20px 40px 20px 40px',
                md: '20px 40px 20px 40px',
                lg: '20px 40px 20px 40px',
                xl: '20px 40px 20px 40px',
                xxl: '20px 40px 20px 40px',
            },
            'col-span': { lg: 6 },
        });
    });

    it('normalizes legacy numeric padding maps when sanitizing elements for write', () => {
        const layoutWithStyle = cloneDeep(layout);
        const paddingOption: ContentSystemStyleOptionSpecification = {
            type: 'string',
            enum: null,
            range: null,
            maxLength: 64,
            default: null,
            breakpointAware: true,
            adminUI: {
                component: 'box-spacing',
                label: 'Padding',
            },
        };

        layoutWithStyle[0].slots!.content[0].style = {
            padding: {
                xs: 20,
                sm: 20,
                md: 20,
                lg: 20,
                xl: 20,
                xxl: 20,
            },
        };

        const sanitizedLayout = sanitizeContentElementLayoutForWrite(layoutWithStyle, {
            padding: paddingOption,
        });

        expect(sanitizedLayout[0].slots!.content[0].style).toEqual({
            padding: {
                xs: '20px 20px 20px 20px',
                sm: '20px 20px 20px 20px',
                md: '20px 20px 20px 20px',
                lg: '20px 20px 20px 20px',
                xl: '20px 20px 20px 20px',
                xxl: '20px 20px 20px 20px',
            },
        });
    });

    it('omits unset style values when sanitizing elements for write', () => {
        const layoutWithStyle = cloneDeep(layout);
        const colSpanOption: ContentSystemStyleOptionSpecification = {
            type: 'integer',
            enum: null,
            range: { min: 1, max: 12 },
            maxLength: null,
            default: null,
            breakpointAware: true,
            adminUI: {
                component: 'number',
                label: 'Column Span',
            },
        };

        layoutWithStyle[0].slots!.content[0].style = {
            'col-span': { md: 1 },
            padding: '',
        };

        const sanitizedLayout = sanitizeContentElementLayoutForWrite(layoutWithStyle, {
            'col-span': colSpanOption,
            padding: {
                ...colSpanOption,
                type: 'string',
            },
        });

        expect(sanitizedLayout[0].slots!.content[0]).not.toHaveProperty('style');
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

    it('preserves flat style values for breakpoint-unaware options when sanitizing', () => {
        const layoutWithStyle = cloneDeep(layout);
        const zIndexOption: ContentSystemStyleOptionSpecification = {
            type: 'integer',
            enum: null,
            range: null,
            maxLength: null,
            default: null,
            breakpointAware: false,
            adminUI: {
                component: 'number',
                label: 'Z-Index',
            },
        };

        layoutWithStyle[0].slots!.content[0].style = {
            'z-index': 10,
        };

        const sanitizedLayout = sanitizeContentElementLayoutForWrite(layoutWithStyle, {
            'z-index': zIndexOption,
        });

        expect(sanitizedLayout[0].slots!.content[0].style).toEqual({
            'z-index': 10,
        });
    });

    it('preserves style when sanitizing elements for write without style options', () => {
        const layoutWithStyle = cloneDeep(layout);
        layoutWithStyle[0].slots!.content[0].style = {
            'col-span': 6,
        };

        const sanitizedLayout = sanitizeContentElementLayoutForWrite(layoutWithStyle);

        expect(sanitizedLayout[0].slots!.content[0].style).toEqual({
            'col-span': 6,
        });
    });

    it('preserves data requirements when sanitizing elements for write', () => {
        const layoutWithDataRequirements = cloneDeep(layout);
        layoutWithDataRequirements[0].slots!.content[1].dataRequirements = {
            media: {
                source: 'entity',
                config: {
                    entityName: 'media',
                    id: 'media-1',
                },
            },
        };

        const sanitizedLayout = sanitizeContentElementLayoutForWrite(layoutWithDataRequirements);

        expect(sanitizedLayout[0].slots!.content[1].dataRequirements).toEqual({
            media: {
                source: 'entity',
                config: {
                    entityName: 'media',
                    id: 'media-1',
                },
            },
        });
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

            applyResolvedContextConsumers([withConsumer, withRequirement], {
                e1: [parent('product', 'product', 'single', true)],
                e2: [parent('product', 'product', 'single', true)],
            });

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
