import type { ContentSystemElementTypeProperty } from 'src/core/service/api/content-system-element-type.api.service';
import {
    getAdminUiHelpText,
    getAdminUiProps,
    getElementPropertyStorageKey,
    getInitialPropertyValue,
    getPropertyControlType,
    isPropertyVisible,
    resolveTranslatableEntry,
    withLanguageEntry,
} from './element-settings.util';

const ANCHOR_LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
const GERMAN_LANGUAGE_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';
const FRENCH_LANGUAGE_ID = 'c3d4e5f60718293a4b5c6d7e8f90a1b2';
const ITALIAN_LANGUAGE_ID = 'd4e5f60718293a4b5c6d7e8f90a1b2c3';
const LANGUAGE_CHAIN = [
    FRENCH_LANGUAGE_ID,
    GERMAN_LANGUAGE_ID,
    ANCHOR_LANGUAGE_ID,
];

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

    it('maps entity multi select properties to entity multi controls', () => {
        expect(
            getPropertyControlType({
                ...stringProperty,
                adminUI: {
                    component: 'entity-multi',
                    entity: 'property_group',
                },
            }),
        ).toBe('entity-multi');

        expect(
            getPropertyControlType({
                ...stringProperty,
                type: 'Shopware\\Core\\Content\\Product\\ProductCollection',
                adminUI: {
                    component: 'sw-entity-multi-id-select',
                    entity: 'product',
                },
            }),
        ).toBe('entity-multi');
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

    it('resolves the entry of the chain head as an own translation', () => {
        expect(
            resolveTranslatableEntry(
                {
                    [FRENCH_LANGUAGE_ID]: 'Bonjour',
                    [ANCHOR_LANGUAGE_ID]: 'Hello',
                },
                LANGUAGE_CHAIN,
            ),
        ).toEqual({
            state: 'own',
            value: 'Bonjour',
        });
    });

    it('resolves an entry the chain head lacks as inherited from the language carrying it', () => {
        expect(resolveTranslatableEntry({ [ANCHOR_LANGUAGE_ID]: 'Hello' }, LANGUAGE_CHAIN)).toEqual({
            state: 'inherited',
            value: 'Hello',
            fromLanguageId: ANCHOR_LANGUAGE_ID,
        });
    });

    it('inherits from the earliest chain language carrying an entry', () => {
        expect(
            resolveTranslatableEntry(
                {
                    // the later chain language is written first, so a map-key-order lookup would answer with the anchor entry
                    [ANCHOR_LANGUAGE_ID]: 'Hello',
                    [GERMAN_LANGUAGE_ID]: 'Hallo',
                },
                LANGUAGE_CHAIN,
            ),
        ).toEqual({
            state: 'inherited',
            value: 'Hallo',
            fromLanguageId: GERMAN_LANGUAGE_ID,
        });
    });

    it('resolves a map carrying no chain language as missing', () => {
        expect(resolveTranslatableEntry({ [ITALIAN_LANGUAGE_ID]: 'Ciao' }, LANGUAGE_CHAIN)).toEqual({
            state: 'missing',
        });
    });

    it('resolves an undefined value as missing', () => {
        expect(resolveTranslatableEntry(undefined, LANGUAGE_CHAIN)).toEqual({ state: 'missing' });
    });

    it.each([
        [
            'a bare string',
            'Hello',
        ],
        [
            'a list',
            ['Hello'],
        ],
    ])('throws when resolving %s instead of a language map', (_label, value) => {
        expect(() => resolveTranslatableEntry(value, LANGUAGE_CHAIN)).toThrow(
            /must be undefined or a non-empty language map of strings/,
        );
    });

    it('sets the entry of the given language and keeps every other entry', () => {
        expect(
            withLanguageEntry(
                {
                    [ANCHOR_LANGUAGE_ID]: 'Hello',
                    [FRENCH_LANGUAGE_ID]: 'Bonjour',
                },
                GERMAN_LANGUAGE_ID,
                'Hallo',
            ),
        ).toEqual({
            [ANCHOR_LANGUAGE_ID]: 'Hello',
            [FRENCH_LANGUAGE_ID]: 'Bonjour',
            [GERMAN_LANGUAGE_ID]: 'Hallo',
        });
    });

    it('removes the entry of the given language when the entry is null', () => {
        expect(
            withLanguageEntry(
                {
                    [ANCHOR_LANGUAGE_ID]: 'Hello',
                    [GERMAN_LANGUAGE_ID]: 'Hallo',
                },
                GERMAN_LANGUAGE_ID,
                null,
            ),
        ).toEqual({ [ANCHOR_LANGUAGE_ID]: 'Hello' });
    });

    it('carries a non-string entry verbatim so the write route judges it', () => {
        expect(
            withLanguageEntry(
                {
                    [ANCHOR_LANGUAGE_ID]: 'Hello',
                    [GERMAN_LANGUAGE_ID]: null,
                },
                ANCHOR_LANGUAGE_ID,
                'Hello again',
            ),
        ).toEqual({
            [ANCHOR_LANGUAGE_ID]: 'Hello again',
            [GERMAN_LANGUAGE_ID]: null,
        });
    });

    it.each([
        [
            'a bare string',
            'Hello',
        ],
        [
            'a list',
            ['Hello'],
        ],
        [
            'null',
            null,
        ],
        [
            'undefined',
            undefined,
        ],
    ])('writes a single-entry language map over %s', (_label, current) => {
        expect(withLanguageEntry(current, ANCHOR_LANGUAGE_ID, 'Hello again')).toEqual({
            [ANCHOR_LANGUAGE_ID]: 'Hello again',
        });
    });

    it('throws when the anchor language entry is removed', () => {
        expect(() => withLanguageEntry({ [ANCHOR_LANGUAGE_ID]: 'Hello' }, ANCHOR_LANGUAGE_ID, null)).toThrow(
            /anchor language entry of a translatable property cannot be removed/,
        );
    });
});
