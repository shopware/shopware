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

    it('creates the repository for the configured adminUI entity', () => {
        const repository = { entityName: 'property_group' };
        const create = jest.fn(() => repository);

        expect(
            methods.getEntityRepository.call(
                {
                    getEntityName: methods.getEntityName,
                    repositoryFactory: { create },
                },
                {
                    adminUI: {
                        component: 'entity-multi-id-select',
                        entity: 'property_group',
                    },
                },
            ),
        ).toBe(repository);
        expect(create).toHaveBeenCalledWith('property_group');
    });

    it('returns no repository without an adminUI entity', () => {
        const create = jest.fn();

        expect(
            methods.getEntityRepository.call(
                {
                    getEntityName: methods.getEntityName,
                    repositoryFactory: { create },
                },
                {
                    adminUI: {
                        component: 'entity-multi-id-select',
                    },
                },
            ),
        ).toBeNull();
        expect(create).not.toHaveBeenCalled();
    });

    it('splits, trims and filters comma separated id list values', () => {
        const value = methods.getIdListValue.call(
            {
                values: {
                    propertyIds: ' id-1 , ,id-2,',
                },
                getPropertyValue: methods.getPropertyValue,
                getControlType: methods.getControlType,
            },
            'propertyIds',
            {
                type: 'string',
                default: null,
                adminUI: {
                    component: 'entity-multi-id-select',
                    entity: 'property_group',
                },
            },
        );

        expect(value).toEqual([
            'id-1',
            'id-2',
        ]);
    });

    it('returns an empty id list for empty and non-string values', () => {
        const property = {
            type: 'string',
            default: null,
            adminUI: {
                component: 'entity-multi-id-select',
                entity: 'property_group',
            },
        };
        const contextFor = (value: unknown) => ({
            values: {
                propertyIds: value,
            },
            getPropertyValue: methods.getPropertyValue,
            getControlType: methods.getControlType,
        });

        expect(methods.getIdListValue.call(contextFor(''), 'propertyIds', property)).toEqual([]);
        expect(methods.getIdListValue.call(contextFor(42), 'propertyIds', property)).toEqual([]);
    });

    it('joins string ids and drops non-string entries when updating an id list', () => {
        const onUpdateField = jest.fn();

        methods.onUpdateIdList.call({ onUpdateField }, 'propertyIds', [
            'id-1',
            42,
            null,
            'id-2',
        ]);

        expect(onUpdateField).toHaveBeenCalledWith('propertyIds', 'id-1,id-2');
    });

    it('persists an empty id list when the update payload is not an array', () => {
        const onUpdateField = jest.fn();

        methods.onUpdateIdList.call({ onUpdateField }, 'propertyIds', 'not-an-array');

        expect(onUpdateField).toHaveBeenCalledWith('propertyIds', '');
    });
});
