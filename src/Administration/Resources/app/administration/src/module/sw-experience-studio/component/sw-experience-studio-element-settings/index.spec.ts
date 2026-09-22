import elementSettingsComponent from './index';

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

    describe('data mapping', () => {
        const textType = {
            properties: {
                text: {},
                headline: {},
            },
        };

        it('reports the mapped path per declared property', () => {
            const mappings = computed.elementMappings.call({
                selectedElement: {
                    id: 'text-element',
                    acceptsContext: {
                        text: {
                            type: 'single',
                            required: false,
                            scope: 'root',
                            sourcePath: 'category.name',
                        },
                    },
                },
                selectedElementType: textType,
            });

            expect(mappings).toEqual({
                text: 'category.name',
            });
        });

        it('ignores a consumer that does not alias a declared property', () => {
            const mappings = computed.elementMappings.call({
                selectedElement: {
                    id: 'text-element',
                    acceptsContext: {
                        product: {
                            type: 'single',
                            required: true,
                            scope: 'parent',
                        },
                    },
                },
                selectedElementType: textType,
            });

            expect(mappings).toEqual({});
        });

        it('emits the declared property key rather than a binding storage key', () => {
            const $emit = jest.fn();

            methods.onUpdateElementMapping.call(
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
                    path: 'product.cover',
                    contextType: 'single',
                    projection: 'product_media_to_media',
                },
            );

            expect($emit).toHaveBeenCalledWith('update-mapping', {
                elementId: 'image-element',
                propertyKey: 'media',
                path: 'product.cover',
                contextType: 'single',
                projection: 'product_media_to_media',
            });
        });

        it('stays silent on a read-only layout', () => {
            const $emit = jest.fn();

            methods.onUpdateElementMapping.call(
                {
                    selectedElement: {
                        id: 'text-element',
                    },
                    selectedElementType: textType,
                    allowEdit: false,
                    $emit,
                },
                {
                    key: 'text',
                    path: 'category.name',
                    contextType: 'single',
                    projection: null,
                },
            );

            expect($emit).not.toHaveBeenCalled();
        });
    });
});
