import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';
import {
    getAdminUiHelpText,
    getAdminUiProps,
    getElementPropertyStorageKey,
    getInitialPropertyValue,
    getPropertyControlType,
    isPropertyVisible,
} from './element-settings.util';

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

    it('uses the resolvedBy storage key from the default entity binding', () => {
        expect(
            getElementPropertyStorageKey(
                {
                    bindingSpecifications: {
                        'core:Sw:Media:Image': {
                            default: true,
                            resolves: {
                                media: {
                                    loader: 'entity',
                                    config: {
                                        entity: 'media',
                                        property: 'mediaId',
                                    },
                                },
                            },
                        },
                    },
                },
                'media',
            ),
        ).toBe('mediaId');
    });

    it('keeps the declared key for properties without a resolvedBy entity binding', () => {
        expect(
            getElementPropertyStorageKey(
                {
                    bindingSpecifications: {
                        'core:Sw:Content:Text': {
                            default: true,
                            resolves: {},
                        },
                    },
                },
                'text',
            ),
        ).toBe('text');
    });

    it('maps boolean properties to switch controls', () => {
        expect(
            getPropertyControlType({
                ...stringProperty,
                type: 'boolean',
            }),
        ).toBe('switch');
    });

    it('maps number and integer properties to number controls', () => {
        expect(
            getPropertyControlType({
                ...stringProperty,
                type: 'number',
            }),
        ).toBe('number');

        expect(
            getPropertyControlType({
                ...stringProperty,
                type: 'integer',
            }),
        ).toBe('number');
    });

    it('maps enum string properties to select controls', () => {
        expect(
            getPropertyControlType({
                ...stringProperty,
                enum: [
                    'left',
                    'center',
                    'right',
                ],
            }),
        ).toBe('select');
    });

    it('maps adminUI select properties to select controls', () => {
        expect(
            getPropertyControlType({
                ...stringProperty,
                adminUI: {
                    component: 'mt-select',
                },
            }),
        ).toBe('select');
    });

    it('maps adminUI color properties to color controls', () => {
        expect(
            getPropertyControlType({
                ...stringProperty,
                adminUI: {
                    component: 'color',
                },
            }),
        ).toBe('color');
    });

    it('maps adminUI radio panel properties to radio panel controls', () => {
        expect(
            getPropertyControlType({
                ...stringProperty,
                adminUI: {
                    component: 'radio-panel',
                },
            }),
        ).toBe('radio-panel');
    });

    it('maps adminUI responsive number properties to responsive number controls', () => {
        expect(
            getPropertyControlType({
                ...stringProperty,
                type: [
                    'integer',
                    'object',
                ],
                adminUI: {
                    component: 'responsive-number',
                },
            }),
        ).toBe('responsive-number');
    });

    it('maps adminUI slider properties to slider controls', () => {
        expect(
            getPropertyControlType({
                ...stringProperty,
                type: 'integer',
                adminUI: {
                    component: 'slider',
                },
            }),
        ).toBe('slider');
    });

    it('maps style option adminUI components to controls', () => {
        expect(
            getPropertyControlType({
                ...stringProperty,
                type: 'integer',
                adminUI: {
                    component: 'number',
                },
            }),
        ).toBe('number');

        expect(
            getPropertyControlType({
                ...stringProperty,
                type: 'boolean',
                adminUI: {
                    component: 'switch',
                },
            }),
        ).toBe('switch');

        expect(
            getPropertyControlType({
                ...stringProperty,
                adminUI: {
                    component: 'text',
                },
            }),
        ).toBe('text');

        expect(
            getPropertyControlType({
                ...stringProperty,
                adminUI: {
                    component: 'box-spacing',
                },
            }),
        ).toBe('box-spacing');
    });

    it('maps entity select properties to entity controls', () => {
        expect(
            getPropertyControlType({
                ...stringProperty,
                type: 'Shopware\\Core\\Content\\Media\\MediaEntity',
                adminUI: {
                    component: 'entity-single-select',
                    entity: 'media',
                },
            }),
        ).toBe('entity');
    });

    it('maps media field properties to media controls', () => {
        expect(
            getPropertyControlType({
                ...stringProperty,
                type: 'Shopware\\Core\\Content\\Media\\MediaEntity',
                adminUI: {
                    component: 'media-field',
                },
            }),
        ).toBe('media');
    });

    it('maps text editor properties to richtext controls', () => {
        expect(
            getPropertyControlType({
                ...stringProperty,
                adminUI: {
                    component: 'mt-text-editor',
                },
            }),
        ).toBe('richtext');
    });

    it('extracts adminUI props when provided', () => {
        expect(
            getAdminUiProps({
                ...stringProperty,
                adminUI: {
                    component: 'mt-text-field',
                    props: {
                        placeholder: 'Example',
                    },
                },
            }),
        ).toEqual({
            placeholder: 'Example',
        });
    });

    it('returns empty adminUI props when missing', () => {
        expect(getAdminUiProps(stringProperty)).toEqual({});
    });

    it('returns adminUI help text when provided', () => {
        expect(
            getAdminUiHelpText({
                ...stringProperty,
                adminUI: {
                    component: 'mt-text-field',
                    helpText: 'sw-experience-studio.elements.grid.columns.helpText',
                },
            }),
        ).toBe('sw-experience-studio.elements.grid.columns.helpText');
    });

    it('returns null when adminUI help text is missing', () => {
        expect(getAdminUiHelpText(stringProperty)).toBeNull();
    });

    it('maps plain string properties to text controls', () => {
        expect(getPropertyControlType(stringProperty)).toBe('text');
    });

    it('returns null for non-primitive property types', () => {
        expect(
            getPropertyControlType({
                ...stringProperty,
                type: 'Shopware\\Core\\Content\\Product\\SalesChannel\\SalesChannelProductEntity',
            }),
        ).toBeNull();
    });

    it('uses current value before defaults', () => {
        expect(
            getInitialPropertyValue(
                {
                    ...stringProperty,
                    default: 'Default',
                },
                'Explicit',
            ),
        ).toBe('Explicit');
    });

    it('falls back to schema default when current value is missing', () => {
        expect(
            getInitialPropertyValue(
                {
                    ...stringProperty,
                    default: 'Default',
                },
                undefined,
            ),
        ).toBe('Default');
    });

    it('returns sensible primitive fallbacks when no value exists', () => {
        expect(
            getInitialPropertyValue(
                {
                    ...stringProperty,
                    type: 'boolean',
                    default: null,
                },
                undefined,
            ),
        ).toBe(false);

        expect(
            getInitialPropertyValue(
                {
                    ...stringProperty,
                    type: 'string',
                    default: null,
                },
                undefined,
            ),
        ).toBe('');

        expect(
            getInitialPropertyValue(
                {
                    ...stringProperty,
                    type: 'number',
                    default: null,
                },
                undefined,
            ),
        ).toBeNull();
    });

    it('hides properties flagged as hidden regardless of values', () => {
        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        hidden: true,
                    },
                },
                {},
            ),
        ).toBe(false);
    });

    it('ignores hidden when it is not strictly true and evaluates visibleWhen instead', () => {
        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        hidden: false,
                        visibleWhen: {
                            field: 'mode',
                            equals: 'explicit',
                        },
                    },
                },
                { mode: 'explicit' },
            ),
        ).toBe(true);
    });

    it('supports visibleWhen equals and notEquals operators', () => {
        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: {
                            field: 'mode',
                            equals: 'explicit',
                        },
                    },
                },
                { mode: 'explicit' },
            ),
        ).toBe(true);

        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: {
                            field: 'mode',
                            notEquals: 'explicit',
                        },
                    },
                },
                { mode: 'auto-fit' },
            ),
        ).toBe(true);
    });

    it('supports visibleWhen in and notIn operators', () => {
        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: {
                            field: 'mode',
                            in: [
                                'auto-fit',
                                'auto-fill',
                            ],
                        },
                    },
                },
                { mode: 'auto-fill' },
            ),
        ).toBe(true);

        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: {
                            field: 'mode',
                            notIn: [
                                'explicit',
                                'max-content',
                            ],
                        },
                    },
                },
                { mode: 'auto-fit' },
            ),
        ).toBe(true);
    });

    it('supports visibleWhen isEmpty and isNotEmpty operators', () => {
        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: {
                            field: 'headline',
                            isEmpty: true,
                        },
                    },
                },
                { headline: '' },
            ),
        ).toBe(true);

        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: {
                            field: 'headline',
                            isNotEmpty: true,
                        },
                    },
                },
                { headline: 'Shopware' },
            ),
        ).toBe(true);
    });

    it('applies AND semantics when visibleWhen is an array', () => {
        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: [
                            {
                                field: 'mode',
                                equals: 'explicit',
                            },
                            {
                                field: 'headline',
                                isNotEmpty: true,
                            },
                        ],
                    },
                },
                {
                    mode: 'explicit',
                    headline: 'Visible',
                },
            ),
        ).toBe(true);

        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: [
                            {
                                field: 'mode',
                                equals: 'explicit',
                            },
                            {
                                field: 'headline',
                                isNotEmpty: true,
                            },
                        ],
                    },
                },
                {
                    mode: 'explicit',
                    headline: '',
                },
            ),
        ).toBe(false);
    });

    it('is fail-safe for malformed visibleWhen conditions', () => {
        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: {
                            field: 'mode',
                            unknownOperator: 'explicit',
                        },
                    },
                } as ContentSystemElementTypeProperty,
                { mode: 'explicit' },
            ),
        ).toBe(true);

        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: {
                            field: 'mode',
                            equals: 'explicit',
                            in: ['explicit'],
                        },
                    },
                },
                { mode: 'explicit' },
            ),
        ).toBe(true);
    });

    it('handles missing fields without crashing', () => {
        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: {
                            field: 'missingField',
                            equals: 'explicit',
                        },
                    },
                },
                { mode: 'explicit' },
            ),
        ).toBe(false);

        expect(
            isPropertyVisible(
                {
                    ...stringProperty,
                    adminUI: {
                        visibleWhen: {
                            field: 'missingField',
                            isEmpty: true,
                        },
                    },
                },
                { mode: 'explicit' },
            ),
        ).toBe(true);
    });
});
