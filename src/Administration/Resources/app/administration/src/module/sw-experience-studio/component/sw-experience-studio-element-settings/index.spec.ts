import elementSettingsComponent from './index';

const ANCHOR_LANGUAGE_ID = '2fbb5fe2e29a4d70aa5854ce7ce3e20b';
const GERMAN_LANGUAGE_ID = 'a1b2c3d4e5f60718293a4b5c6d7e8f90';

describe('module/sw-experience-studio/component/sw-experience-studio-element-settings', () => {
    const computed = (
        elementSettingsComponent as unknown as {
            computed: Record<string, (...args: unknown[]) => unknown>;
        }
    ).computed;
    const methods = (
        elementSettingsComponent as unknown as {
            methods: Record<string, (...args: unknown[]) => unknown>;
        }
    ).methods;
    const imageType = {
        properties: {
            media: {},
        },
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
    };

    it('reads resolved fields from their storage key', () => {
        const values = computed.elementPropertyValues.call({
            selectedElement: {
                properties: {
                    mediaId: 'media-id',
                },
            },
            selectedElementType: imageType,
        });

        expect(values).toEqual({
            media: 'media-id',
            mediaId: 'media-id',
        });
    });

    it('emits resolved fields using their storage key', () => {
        const $emit = jest.fn();

        methods.onUpdateElementField.call(
            {
                selectedElement: {
                    id: 'image-element',
                },
                selectedElementType: imageType,
                allowEdit: true,
                $emit,
            },
            {
                key: 'media',
                value: 'media-id',
            },
        );

        expect($emit).toHaveBeenCalledWith('update-properties', {
            elementId: 'image-element',
            properties: {
                mediaId: 'media-id',
            },
        });
    });

    const textType = {
        properties: {
            text: {
                type: 'string',
                translatable: true,
                default: 'Placeholder',
            },
        },
    };

    it('presents the anchor language entry of a translatable property to the field controls', () => {
        const values = computed.elementPropertyValues.call({
            selectedElement: {
                properties: {
                    text: {
                        [GERMAN_LANGUAGE_ID]: 'Hallo',
                        [ANCHOR_LANGUAGE_ID]: 'Hello',
                    },
                },
            },
            selectedElementType: textType,
        });

        expect(values).toEqual({
            text: 'Hello',
        });
    });

    it('leaves an unauthored translatable property absent so the declared default still applies', () => {
        const values = computed.elementPropertyValues.call({
            selectedElement: {
                properties: {},
            },
            selectedElementType: textType,
        });

        expect(values).toEqual({});
    });

    it('resolves a translatable property to its anchor entry when evaluating field visibility', () => {
        const fields = computed.elementFields.call({
            selectedElement: {
                properties: {
                    text: {
                        [ANCHOR_LANGUAGE_ID]: 'Hello',
                    },
                },
            },
            selectedElementType: {
                properties: {
                    text: textType.properties.text,
                    subline: {
                        type: 'string',
                        translatable: false,
                        adminUI: {
                            visibleWhen: {
                                field: 'text',
                                equals: 'Hello',
                            },
                        },
                    },
                },
            },
        });

        expect(fields).toEqual([
            {
                key: 'text',
                property: textType.properties.text,
                breakpointAware: false,
            },
            {
                key: 'subline',
                property: {
                    type: 'string',
                    translatable: false,
                    adminUI: {
                        visibleWhen: {
                            field: 'text',
                            equals: 'Hello',
                        },
                    },
                },
                breakpointAware: false,
            },
        ]);
    });

    it('emits a translatable property as a language map that keeps the other entries', () => {
        const $emit = jest.fn();

        methods.onUpdateElementField.call(
            {
                selectedElement: {
                    id: 'text-element',
                    properties: {
                        text: {
                            [ANCHOR_LANGUAGE_ID]: 'Hello',
                            [GERMAN_LANGUAGE_ID]: 'Hallo',
                        },
                    },
                },
                selectedElementType: textType,
                allowEdit: true,
                $emit,
            },
            {
                key: 'text',
                value: 'Hello again',
            },
        );

        expect($emit).toHaveBeenCalledWith('update-properties', {
            elementId: 'text-element',
            properties: {
                text: {
                    [ANCHOR_LANGUAGE_ID]: 'Hello again',
                    [GERMAN_LANGUAGE_ID]: 'Hallo',
                },
            },
        });
    });

    const captionType = {
        properties: {
            caption: {
                type: 'string',
                translatable: true,
                default: 'Placeholder',
            },
        },
    };

    it('presents the anchor language entry of a translatable property under a key other than text', () => {
        const values = computed.elementPropertyValues.call({
            selectedElement: {
                properties: {
                    caption: {
                        [GERMAN_LANGUAGE_ID]: 'Bildunterschrift',
                        [ANCHOR_LANGUAGE_ID]: 'Caption',
                    },
                },
            },
            selectedElementType: captionType,
        });

        expect(values).toEqual({
            caption: 'Caption',
        });
    });

    it('emits a translatable property under a key other than text as a language map that keeps the other entries', () => {
        const $emit = jest.fn();

        methods.onUpdateElementField.call(
            {
                selectedElement: {
                    id: 'caption-element',
                    properties: {
                        caption: {
                            [ANCHOR_LANGUAGE_ID]: 'Caption',
                            [GERMAN_LANGUAGE_ID]: 'Bildunterschrift',
                        },
                    },
                },
                selectedElementType: captionType,
                allowEdit: true,
                $emit,
            },
            {
                key: 'caption',
                value: 'Caption updated',
            },
        );

        expect($emit).toHaveBeenCalledWith('update-properties', {
            elementId: 'caption-element',
            properties: {
                caption: {
                    [ANCHOR_LANGUAGE_ID]: 'Caption updated',
                    [GERMAN_LANGUAGE_ID]: 'Bildunterschrift',
                },
            },
        });
    });

    it('emits a non-translatable property as the bare control value', () => {
        const $emit = jest.fn();

        methods.onUpdateElementField.call(
            {
                selectedElement: {
                    id: 'text-element',
                    properties: {
                        headline: 'Hello',
                    },
                },
                selectedElementType: {
                    properties: {
                        headline: {
                            type: 'string',
                            translatable: false,
                        },
                    },
                },
                allowEdit: true,
                $emit,
            },
            {
                key: 'headline',
                value: 'Hello again',
            },
        );

        expect($emit).toHaveBeenCalledWith('update-properties', {
            elementId: 'text-element',
            properties: {
                headline: 'Hello again',
            },
        });
    });

    it('keeps breakpoint-aware box spacing properties in the element settings', () => {
        const fields = computed.elementFields.call({
            selectedElement: {
                properties: {},
            },
            selectedElementType: {
                properties: {
                    padding: {
                        type: [
                            'string',
                            'object',
                        ],
                        adminUI: {
                            component: 'box-spacing',
                            breakpointAware: true,
                        },
                    },
                },
            },
        });

        expect(fields).toEqual([
            {
                key: 'padding',
                property: {
                    type: [
                        'string',
                        'object',
                    ],
                    adminUI: {
                        component: 'box-spacing',
                        breakpointAware: true,
                    },
                },
                breakpointAware: true,
            },
        ]);
    });
});
