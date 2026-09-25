import settingsFieldsComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-settings-fields', () => {
    const computed = (
        settingsFieldsComponent as unknown as {
            computed: Record<string, (...args: unknown[]) => unknown>;
        }
    ).computed;
    const methods = (
        settingsFieldsComponent as unknown as {
            methods: Record<string, (...args: unknown[]) => unknown>;
        }
    ).methods;

    it('groups fields by panel while preserving their order', () => {
        const fields = [
            {
                key: 'mode',
                property: {
                    adminUI: {
                        panel: 'general',
                    },
                },
            },
            {
                key: 'padding',
                property: {
                    adminUI: {
                        panel: 'spacing',
                    },
                },
            },
            {
                key: 'columns',
                property: {
                    adminUI: {
                        panel: 'general',
                    },
                },
            },
            {
                key: 'custom',
                property: {},
            },
        ];

        const panels = computed.fieldPanels.call({
            fields,
            showPanels: true,
            getFieldPanelTechnicalName: methods.getFieldPanelTechnicalName,
        }) as Array<{
            technicalName: string | null;
            fields: Array<{ key: string }>;
        }>;

        expect(
            panels.map((panel) => ({
                technicalName: panel.technicalName,
                fields: panel.fields.map((field) => field.key),
            })),
        ).toEqual([
            {
                technicalName: 'general',
                fields: [
                    'mode',
                    'columns',
                ],
            },
            {
                technicalName: 'spacing',
                fields: ['padding'],
            },
            {
                technicalName: null,
                fields: ['custom'],
            },
        ]);
    });

    it('keeps style option fields in one plain group', () => {
        const fields = [
            {
                key: 'display',
                property: {},
            },
            {
                key: 'margin',
                property: {
                    adminUI: {
                        panel: 'spacing',
                    },
                },
            },
        ];

        const panels = computed.fieldPanels.call({
            fields,
            showPanels: false,
        });

        expect(panels).toEqual([
            {
                key: '__default__',
                technicalName: null,
                fields,
            },
        ]);
    });

    it('prefers an error violation for the field', () => {
        const warning = {
            code: 'unresolved_optional',
            severity: 'warning',
            key: 'text',
        };
        const error = {
            code: 'invalid_mapping',
            severity: 'error',
            key: 'text',
        };

        expect(
            methods.getFieldViolation.call(
                {
                    violations: [
                        warning,
                        error,
                        { code: 'invalid_mapping', severity: 'error', key: 'media' },
                    ],
                },
                { key: 'text' },
            ),
        ).toBe(error);
    });

    it('builds element-specific and default panel snippet keys', () => {
        expect(
            methods.getPanelSnippetKey.call(
                {
                    selectedElementType: {
                        name: 'Sw:Grid:Container',
                    },
                },
                {
                    technicalName: 'spacing',
                },
            ),
        ).toBe('sw-experience-studio.elements.sw-grid-container.panels.spacing');

        expect(
            methods.getPanelSnippetKey.call(
                {
                    selectedElementType: null,
                },
                {
                    technicalName: null,
                },
            ),
        ).toBe('sw-experience-studio.detail.elementSettings.panelGeneral');
    });

    it('translates the generated panel snippet key', () => {
        const $t = jest.fn(() => 'Spacing');
        const getPanelSnippetKey = jest.fn(() => 'sw-experience-studio.elements.sw-grid-container.panels.spacing');

        expect(
            methods.getPanelTitle.call(
                {
                    $t,
                    getPanelSnippetKey,
                },
                {
                    technicalName: 'spacing',
                },
            ),
        ).toBe('Spacing');
        expect($t).toHaveBeenCalledWith('sw-experience-studio.elements.sw-grid-container.panels.spacing');
    });

    it('expands only the general panel by default', () => {
        const context = {
            showPanels: true,
        };

        expect(methods.isPanelExpandedByDefault.call(context, { technicalName: 'general' })).toBe(true);
        expect(methods.isPanelExpandedByDefault.call(context, { technicalName: 'spacing' })).toBe(false);
        expect(methods.isPanelExpandedByDefault.call(context, { technicalName: null })).toBe(true);
        expect(
            methods.isPanelExpandedByDefault.call(
                {
                    showPanels: false,
                },
                { technicalName: 'spacing' },
            ),
        ).toBe(true);
    });

    it('maps corner radius previews from radio panel options', () => {
        expect(
            methods.getRadioPanelOptions.call(
                {
                    getControlProps: methods.getControlProps,
                },
                {
                    adminUI: {
                        props: {
                            options: [
                                {
                                    value: '8px',
                                    label: 'Medium',
                                    cornerRadius: '8px',
                                },
                            ],
                        },
                    },
                },
            ),
        ).toEqual([
            {
                value: '8px',
                label: 'Medium',
                cornerRadius: '8px',
                icon: undefined,
                description: undefined,
                disabled: undefined,
            },
        ]);
    });

    it('prefers declared options for select controls', () => {
        expect(
            methods.getSelectOptions.call(
                {
                    getControlProps: methods.getControlProps,
                },
                {
                    enum: [
                        'spaceBetween',
                        'spaceEvenly',
                    ],
                    adminUI: {
                        props: {
                            options: [
                                {
                                    value: 'normal',
                                    label: 'Normal',
                                    icon: 'regular-circle',
                                },
                                {
                                    value: 'spaceBetween',
                                    label: 'Space between',
                                    icon: 'regular-align-justify',
                                },
                            ],
                        },
                    },
                },
            ),
        ).toEqual([
            {
                value: 'normal',
                label: 'Normal',
                icon: 'regular-circle',
            },
            {
                value: 'spaceBetween',
                label: 'Space between',
                icon: 'regular-align-justify',
            },
        ]);
    });

    it('derives select options from the enum when no options are declared', () => {
        expect(
            methods.getSelectOptions.call(
                {
                    getControlProps: methods.getControlProps,
                },
                {
                    enum: [
                        'spaceBetween',
                        'spaceEvenly',
                    ],
                },
            ),
        ).toEqual([
            {
                value: 'spaceBetween',
                label: 'spaceBetween',
            },
            {
                value: 'spaceEvenly',
                label: 'spaceEvenly',
            },
        ]);
    });

    it('presents a comma-separated id list to the entity picker as an array', () => {
        const field = {
            key: 'propertyAllowlist',
            property: {
                type: 'string',
                adminUI: {
                    component: 'entity-multi',
                    entity: 'property_group',
                },
            },
        };
        const vm = {
            selectedElementType: null,
            values: {
                propertyAllowlist: 'a,b',
            },
            getRawPropertyValue: methods.getRawPropertyValue,
        };

        expect(methods.getEntityMultiCodec.call(vm, field)).toBe('csv');
        expect(methods.getEntityMultiValue.call(vm, field.key)).toEqual([
            'a',
            'b',
        ]);
    });

    it('joins picked ids into a comma-separated list for a string property', () => {
        const $emit = jest.fn();
        const field = {
            key: 'propertyAllowlist',
            property: {
                type: 'string',
                adminUI: {
                    component: 'entity-multi',
                    entity: 'property_group',
                },
            },
        };
        const vm = {
            $emit,
            allowEdit: true,
            selectedElementType: null,
            getEntityMultiCodec: methods.getEntityMultiCodec,
            onUpdateField: methods.onUpdateField,
        };

        methods.onUpdateEntityMultiField.call(vm, field, [
            'a',
            'b',
        ]);

        expect($emit).toHaveBeenCalledWith('update-field', {
            key: 'propertyAllowlist',
            value: 'a,b',
        });
    });

    it('presents a resolved id array to the entity picker unchanged', () => {
        const field = {
            key: 'products',
            property: {
                type: 'array',
                adminUI: {
                    component: 'entity-multi',
                    entity: 'product',
                },
            },
        };
        const vm = {
            selectedElementType: {
                bindingSpecifications: {
                    productListing: {
                        default: true,
                        resolves: {
                            products: {
                                loader: 'entity_collection',
                                config: {
                                    property: 'productIds',
                                },
                            },
                        },
                    },
                },
            },
            values: {
                products: [
                    'a',
                    'b',
                ],
            },
            getRawPropertyValue: methods.getRawPropertyValue,
        };

        expect(methods.getEntityMultiCodec.call(vm, field)).toBe('array');
        expect(methods.getEntityMultiValue.call(vm, field.key)).toEqual([
            'a',
            'b',
        ]);
    });

    it('emits picked ids as an array for an entity collection property', () => {
        const $emit = jest.fn();
        const field = {
            key: 'products',
            property: {
                type: 'array',
                adminUI: {
                    component: 'entity-multi',
                    entity: 'product',
                },
            },
        };
        const vm = {
            $emit,
            allowEdit: true,
            selectedElementType: {
                bindingSpecifications: {
                    productListing: {
                        default: true,
                        resolves: {
                            products: {
                                loader: 'entity_collection',
                                config: {
                                    property: 'productIds',
                                },
                            },
                        },
                    },
                },
            },
            getEntityMultiCodec: methods.getEntityMultiCodec,
            onUpdateField: methods.onUpdateField,
        };

        methods.onUpdateEntityMultiField.call(vm, field, [
            'a',
            'b',
        ]);

        expect($emit).toHaveBeenCalledWith('update-field', {
            key: 'products',
            value: [
                'a',
                'b',
            ],
        });
    });

    it('resolves no codec for an entity-multi property matching neither stored shape', () => {
        const field = {
            key: 'products',
            property: {
                type: 'array',
                adminUI: {
                    component: 'entity-multi',
                    entity: 'product',
                },
            },
        };
        const vm = {
            selectedElementType: {
                bindingSpecifications: {
                    productListing: {
                        default: false,
                        resolves: {
                            products: {
                                loader: 'entity_collection',
                                config: {
                                    property: 'productIds',
                                },
                            },
                        },
                    },
                },
            },
        };

        expect(methods.getControlType.call(vm, field.property)).toBe('entity-multi');
        expect(methods.getEntityMultiCodec.call(vm, field)).toBeNull();
    });

    it('uses a shared structured default for breakpoint-aware box spacing', () => {
        const value = methods.getResponsiveFallbackValue.call(
            {
                getControlType: methods.getControlType,
            },
            {
                type: [
                    'string',
                    'object',
                ],
                default: null,
                adminUI: {
                    component: 'box-spacing',
                },
                properties: {
                    xs: {
                        default: '0 20px 0 20px',
                    },
                    sm: {
                        default: '0 20px 0 20px',
                    },
                },
            },
        );

        expect(value).toBe('0 20px 0 20px');
    });

    it('uses explicit zero spacing when a breakpoint-aware box-spacing property has null defaults', () => {
        const value = methods.getResponsiveFallbackValue.call(
            {
                getControlType: methods.getControlType,
            },
            {
                type: [
                    'string',
                    'object',
                ],
                default: null,
                adminUI: {
                    component: 'box-spacing',
                },
                properties: {
                    xs: {
                        default: null,
                    },
                    sm: {
                        default: null,
                    },
                },
            },
        );

        expect(value).toBe('0 0 0 0');
    });

    describe('data mapping', () => {
        const mappableTextField = {
            key: 'text',
            property: {
                type: 'string',
                contextTypes: ['single'],
                mappable: true,
                title: 'Text',
                adminUI: null,
            },
        };

        const staticTextField = {
            key: 'headline',
            property: {
                type: 'string',
                contextTypes: ['single'],
                mappable: false,
                title: 'Headline',
                adminUI: null,
            },
        };

        const mappableGalleryField = {
            key: 'mediaItems',
            property: {
                type: 'Shopware\\Core\\Content\\Media\\MediaCollection',
                contextTypes: ['collection'],
                mappable: true,
                title: 'Media',
                adminUI: null,
            },
        };

        const categoryNameCandidate = {
            path: 'category.name',
            label: 'sw-experience-studio.mapping.category.name.label',
            description: 'sw-experience-studio.mapping.category.name.description',
            group: 'basic',
            valueType: 'string',
            contextType: 'single',
            projection: null,
        };

        const mediaCandidate = {
            path: 'category.media',
            label: 'sw-experience-studio.mapping.category.media.label',
            description: 'sw-experience-studio.mapping.category.media.description',
            group: 'media',
            valueType: 'Shopware\\Core\\Content\\Media\\MediaEntity',
            contextType: 'single',
            projection: null,
        };

        it('offers only candidates that can fill a mappable property', () => {
            const candidates = methods.getMappingCandidatesForField.call(
                {
                    mappingCandidates: [
                        categoryNameCandidate,
                        mediaCandidate,
                    ],
                },
                mappableTextField,
            ) as Array<{ path: string }>;

            expect(candidates.map((candidate) => candidate.path)).toEqual(['category.name']);
        });

        // Both are class types, so the class check admits the single image for the gallery; only the
        // context type tells them apart, and picking the image rendered an empty gallery.
        it('offers no single-media candidate for a collection property', () => {
            const candidates = methods.getMappingCandidatesForField.call(
                {
                    mappingCandidates: [
                        mediaCandidate,
                        {
                            ...mediaCandidate,
                            path: 'product.media',
                            valueType: 'Shopware\\Core\\Content\\Media\\MediaCollection',
                            contextType: 'collection',
                        },
                    ],
                },
                mappableGalleryField,
            ) as Array<{ path: string }>;

            expect(candidates.map((candidate) => candidate.path)).toEqual(['product.media']);
        });

        it('offers nothing for a property that did not opt in', () => {
            const candidates = methods.getMappingCandidatesForField.call(
                {
                    mappingCandidates: [categoryNameCandidate],
                },
                staticTextField,
            ) as unknown[];

            expect(candidates).toEqual([]);
        });

        it('routes an inlineMappable property to the inline text field, not the whole-field chip', () => {
            const inlineTextField = {
                key: 'text',
                property: {
                    type: 'string',
                    contextTypes: ['single'],
                    mappable: false,
                    inlineMappable: true,
                    title: 'Text',
                    adminUI: { component: 'text-editor' },
                },
            };

            expect(methods.isInlineMappableField.call({}, inlineTextField)).toBe(true);
            expect(methods.isInlineMappableField.call({}, mappableTextField)).toBe(false);
            expect(methods.isInlineMappableField.call({}, staticTextField)).toBe(false);

            // Nothing may be mapped onto it as a whole, so the map action never appears alongside the editor.
            expect(
                methods.canMapField.call(
                    {
                        mappingCandidates: [categoryNameCandidate],
                        mappings: {},
                        getMappingCandidatesForField: methods.getMappingCandidatesForField,
                        isFieldMapped: methods.isFieldMapped,
                    },
                    inlineTextField,
                ),
            ).toBe(false);
        });

        it('offers only stringifiable candidates for inline mapping, regardless of property', () => {
            const candidates = computed.inlineMappingCandidates.call({
                mappingCandidates: [
                    categoryNameCandidate,
                    mediaCandidate,
                ],
            }) as Array<{ path: string }>;

            expect(candidates.map((candidate) => candidate.path)).toEqual(['category.name']);
        });

        it('hides the map action once the property is mapped', () => {
            const context = {
                mappingCandidates: [categoryNameCandidate],
                mappings: { text: 'category.name' },
                getMappingCandidatesForField: methods.getMappingCandidatesForField,
                isFieldMapped: methods.isFieldMapped,
            };

            expect(methods.canMapField.call(context, mappableTextField)).toBe(false);
            expect(methods.isFieldMapped.call(context, mappableTextField)).toBe(true);
        });

        it('hides the map action when the catalogue offers nothing usable', () => {
            const context = {
                mappingCandidates: [mediaCandidate],
                mappings: {},
                getMappingCandidatesForField: methods.getMappingCandidatesForField,
                isFieldMapped: methods.isFieldMapped,
            };

            expect(methods.canMapField.call(context, mappableTextField)).toBe(false);
        });

        it('labels a mapping with its snippet, and with the raw path when the catalogue lost it', () => {
            const context = {
                mappingCandidates: [categoryNameCandidate],
                mappings: {
                    text: 'category.name',
                    headline: 'product.name',
                },
                getFieldMappingPath: methods.getFieldMappingPath,
                $te: (key: string) => key === categoryNameCandidate.label,
                $t: () => 'Category name',
            };

            expect(methods.getFieldMappingLabel.call(context, mappableTextField)).toBe('Category name');
            expect(methods.getFieldMappingLabel.call(context, staticTextField)).toBe('product.name');
        });

        it('labels a custom-field mapping from its configured translations', () => {
            const customFieldCandidate = {
                ...categoryNameCandidate,
                path: 'product.customFields.material',
                label: 'material',
                labelTranslations: { custom: 'Material' },
                group: 'customFields',
            };
            const context = {
                mappingCandidates: [customFieldCandidate],
                mappings: {
                    text: 'product.customFields.material',
                },
                getFieldMappingPath: methods.getFieldMappingPath,
                $te: () => false,
                $t: (key: string) => key,
            };

            expect(methods.getFieldMappingLabel.call(context, mappableTextField)).toBe('Material');
        });

        it('emits the chosen path together with its context type and closes the modal', () => {
            const emitted: unknown[] = [];
            const context = {
                allowEdit: true,
                mappingModalFieldKey: 'text',
                mappingModalField: mappableTextField,
                $emit: (event: string, payload: unknown) => emitted.push([
                    event,
                    payload,
                ]),
            };

            methods.onSelectMapping.call(context, categoryNameCandidate);

            expect(context.mappingModalFieldKey).toBeNull();
            expect(emitted).toEqual([
                [
                    'update-mapping',
                    {
                        key: 'text',
                        path: 'category.name',
                        contextType: 'single',
                        projection: null,
                    },
                ],
            ]);
        });

        // The server rejects a mapping whose projection differs from its candidate's, so the pick has to
        // carry the candidate's verbatim rather than the Administration deciding anything about it.
        it('passes the chosen candidate projection on unchanged', () => {
            const emitted: unknown[] = [];
            const context = {
                allowEdit: true,
                mappingModalFieldKey: 'text' as string | null,
                mappingModalField: mappableTextField,
                $emit: (event: string, payload: unknown) => emitted.push([
                    event,
                    payload,
                ]),
            };

            methods.onSelectMapping.call(context, {
                ...categoryNameCandidate,
                path: 'product.cover',
                projection: 'product_media_to_media',
            });

            expect(emitted).toEqual([
                [
                    'update-mapping',
                    {
                        key: 'text',
                        path: 'product.cover',
                        contextType: 'single',
                        projection: 'product_media_to_media',
                    },
                ],
            ]);
        });

        it('emits a null path to unmap', () => {
            const emitted: unknown[] = [];

            methods.onUnmapField.call(
                {
                    allowEdit: true,
                    $emit: (event: string, payload: unknown) => emitted.push([
                        event,
                        payload,
                    ]),
                },
                mappableTextField,
            );

            expect(emitted).toEqual([
                [
                    'update-mapping',
                    {
                        key: 'text',
                        path: null,
                        contextType: null,
                        projection: null,
                    },
                ],
            ]);
        });

        it('stays silent on a read-only layout', () => {
            const emitted: unknown[] = [];
            const context = {
                allowEdit: false,
                mappingModalFieldKey: null as string | null,
                mappingModalField: mappableTextField,
                $emit: (event: string, payload: unknown) => emitted.push([
                    event,
                    payload,
                ]),
            };

            methods.onOpenMappingModal.call(context, mappableTextField);
            methods.onUnmapField.call(context, mappableTextField);
            methods.onSelectMapping.call(context, categoryNameCandidate);

            expect(context.mappingModalFieldKey).toBeNull();
            expect(emitted).toEqual([]);
        });
    });
});
