import settingsFieldsComponent from '../index';

const methods = (
    settingsFieldsComponent as unknown as {
        methods: Record<string, (...args: unknown[]) => unknown>;
    }
).methods;

describe('module/sw-experience-studio/component/sw-experience-studio-settings-fields field controls', () => {
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

    it('forwards a mapping candidate retry request from the modal', () => {
        const $emit = jest.fn();

        methods.onRetryMappingCandidates.call({ $emit });

        expect($emit).toHaveBeenCalledWith('retry-mapping-candidates');
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
});
