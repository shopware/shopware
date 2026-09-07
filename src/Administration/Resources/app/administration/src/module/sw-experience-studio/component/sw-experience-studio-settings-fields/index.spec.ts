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
});
