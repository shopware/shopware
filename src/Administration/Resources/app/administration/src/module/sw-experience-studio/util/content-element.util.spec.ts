import type { ContentSystemStyleOptionSpecification } from 'src/core/service/api/content-system-style-option.api.service';
import type { ContentElementNode } from '../types/content-element.types';
import {
    applyDeclaredContextConsumers,
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

    describe('applyDeclaredContextConsumers', () => {
        // The price element declares it consumes the `product` context; the grid and text declare nothing.
        const declaredByType: Record<string, Record<string, unknown>> = {
            'Sw:Product:PriceDisplay': { product: { type: 'single', required: true } },
        };

        const resolveAcceptsContext = (component: string): Record<string, unknown> | null =>
            declaredByType[component] ?? null;

        it('writes the declared consumer onto an element that declares one', () => {
            const price: ContentElementNode = { id: 'p1', component: 'Sw:Product:PriceDisplay' };

            applyDeclaredContextConsumers([price], resolveAcceptsContext);

            expect(price.acceptsContext).toEqual({
                product: { type: 'single', required: true },
            });
        });

        it('writes declared consumers onto nested elements, not their containers', () => {
            const price: ContentElementNode = { id: 'p1', component: 'Sw:Product:PriceDisplay' };
            const grid: ContentElementNode = { id: 'g1', component: 'Sw:Grid', slots: { default: [price] } };

            applyDeclaredContextConsumers([grid], resolveAcceptsContext);

            // The grid declares no context, so it stays clean — only elements that ask for it get it.
            expect(grid.acceptsContext).toBeUndefined();
            expect(price.acceptsContext).toEqual({
                product: { type: 'single', required: true },
            });
        });

        it('leaves an element untouched when its type declares no context', () => {
            const grid: ContentElementNode = { id: 'g1', component: 'Sw:Grid' };

            applyDeclaredContextConsumers([grid], resolveAcceptsContext);

            expect(grid.acceptsContext).toBeUndefined();
        });

        it('respects an authored consumer for the same key and does not override it', () => {
            const authored = { product: { type: 'single', required: true, propertyAlias: 'item' } };
            const price: ContentElementNode = {
                id: 'p1',
                component: 'Sw:Product:PriceDisplay',
                acceptsContext: cloneDeep(authored),
            };

            applyDeclaredContextConsumers([price], resolveAcceptsContext);

            expect(price.acceptsContext).toEqual(authored);
        });

        it('respects an authored data requirement for the same key and does not add a consumer', () => {
            const price: ContentElementNode = {
                id: 'p1',
                component: 'Sw:Product:PriceDisplay',
                dataRequirements: { product: { source: 'entity', config: { entityName: 'product', id: 'abc' } } },
            };

            applyDeclaredContextConsumers([price], resolveAcceptsContext);

            expect(price.acceptsContext).toBeUndefined();
        });

        it('adds a declared key while preserving an unrelated authored consumer', () => {
            const price: ContentElementNode = {
                id: 'p1',
                component: 'Sw:Product:PriceDisplay',
                acceptsContext: { salesChannel: { type: 'single', required: false } },
            };

            applyDeclaredContextConsumers([price], resolveAcceptsContext);

            expect(price.acceptsContext).toEqual({
                salesChannel: { type: 'single', required: false },
                product: { type: 'single', required: true },
            });
        });

        it('is idempotent across repeated runs', () => {
            const price: ContentElementNode = { id: 'p1', component: 'Sw:Product:PriceDisplay' };

            applyDeclaredContextConsumers([price], resolveAcceptsContext);
            const first = cloneDeep(price.acceptsContext);
            applyDeclaredContextConsumers([price], resolveAcceptsContext);

            expect(price.acceptsContext).toEqual(first);
        });
    });
});
