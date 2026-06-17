import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';
import { getAdminUiProps, getInitialPropertyValue, getPropertyControlType } from './element-settings.util';

describe('module/sw-experience-studio/util/element-settings.util', () => {
    const stringProperty: ContentSystemElementTypeProperty = {
        type: 'string',
        translatable: false,
        enum: null,
        default: null,
        required: false,
        title: 'Headline',
        description: 'Headline text',
        adminUI: null,
    };

    it('maps boolean properties to switch controls', () => {
        expect(getPropertyControlType({
            ...stringProperty,
            type: 'boolean',
        })).toBe('switch');
    });

    it('maps number and integer properties to number controls', () => {
        expect(getPropertyControlType({
            ...stringProperty,
            type: 'number',
        })).toBe('number');

        expect(getPropertyControlType({
            ...stringProperty,
            type: 'integer',
        })).toBe('number');
    });

    it('maps enum string properties to select controls', () => {
        expect(getPropertyControlType({
            ...stringProperty,
            enum: ['left', 'center', 'right'],
        })).toBe('select');
    });

    it('maps adminUI select properties to select controls', () => {
        expect(getPropertyControlType({
            ...stringProperty,
            adminUI: {
                component: 'mt-select',
            },
        })).toBe('select');
    });

    it('maps entity select properties to entity controls', () => {
        expect(getPropertyControlType({
            ...stringProperty,
            type: 'Shopware\\Core\\Content\\Media\\MediaEntity',
            adminUI: {
                component: 'entity-single-select',
                entity: 'media',
            },
        })).toBe('entity');
    });

    it('maps media field properties to media controls', () => {
        expect(getPropertyControlType({
            ...stringProperty,
            type: 'Shopware\\Core\\Content\\Media\\MediaEntity',
            adminUI: {
                component: 'media-field',
            },
        })).toBe('media');
    });

    it('maps text editor properties to richtext controls', () => {
        expect(getPropertyControlType({
            ...stringProperty,
            adminUI: {
                component: 'mt-text-editor',
            },
        })).toBe('richtext');
    });

    it('extracts adminUI props when provided', () => {
        expect(getAdminUiProps({
            ...stringProperty,
            adminUI: {
                component: 'mt-text-field',
                props: {
                    placeholder: 'Example',
                },
            },
        })).toEqual({
            placeholder: 'Example',
        });
    });

    it('returns empty adminUI props when missing', () => {
        expect(getAdminUiProps(stringProperty)).toEqual({});
    });

    it('maps plain string properties to text controls', () => {
        expect(getPropertyControlType(stringProperty)).toBe('text');
    });

    it('returns null for non-primitive property types', () => {
        expect(getPropertyControlType({
            ...stringProperty,
            type: 'Shopware\\Core\\Content\\Product\\SalesChannel\\SalesChannelProductEntity',
        })).toBeNull();
    });

    it('uses current value before defaults', () => {
        expect(getInitialPropertyValue({
            ...stringProperty,
            default: 'Default',
        }, 'Explicit')).toBe('Explicit');
    });

    it('falls back to schema default when current value is missing', () => {
        expect(getInitialPropertyValue({
            ...stringProperty,
            default: 'Default',
        }, undefined)).toBe('Default');
    });

    it('returns sensible primitive fallbacks when no value exists', () => {
        expect(getInitialPropertyValue({
            ...stringProperty,
            type: 'boolean',
            default: null,
        }, undefined)).toBe(false);

        expect(getInitialPropertyValue({
            ...stringProperty,
            type: 'string',
            default: null,
        }, undefined)).toBe('');

        expect(getInitialPropertyValue({
            ...stringProperty,
            type: 'number',
            default: null,
        }, undefined)).toBeNull();
    });
});
