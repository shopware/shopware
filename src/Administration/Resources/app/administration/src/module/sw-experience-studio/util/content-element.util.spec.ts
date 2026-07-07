import type { ContentSystemStyleOptionSpecification } from 'src/core/service/api/content-system-style-option.api.service';
import type { ContentElementNode } from '../types/content-element.types';
import {
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
                component: 'text',
                label: 'Padding',
            },
        };

        layoutWithStyle[0].slots!.content[0].style = {
            padding: '0 8px',
            'col-span': { lg: 6 },
        };

        const sanitizedLayout = sanitizeContentElementLayoutForWrite(layoutWithStyle, {
            padding: paddingOption,
            'col-span': {
                ...paddingOption,
                type: 'integer',
                range: { min: 1, max: 12 },
            },
        });

        expect(sanitizedLayout[0].slots!.content[0].style).toEqual({
            padding: {
                xs: '0 8px',
                sm: '0 8px',
                md: '0 8px',
                lg: '0 8px',
                xl: '0 8px',
                xxl: '0 8px',
            },
            'col-span': { lg: 6 },
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
});
