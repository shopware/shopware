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

    it('translates explicit field, help and option references without changing option values', () => {
        const translations: Record<string, string> = {
            'plugin.title': 'Ausrichtung',
            'plugin.help': 'Hilfe',
            'plugin.option': 'Anfang',
            'plugin.description': 'Beschreibung',
        };
        const context = {
            $te: (key: string) => key in translations,
            $t: (key: string) => translations[key],
        };
        const property = {
            title: 'Alignment',
            titleSnippet: 'plugin.title',
            adminUI: {
                helpText: 'Help',
                helpTextSnippet: 'plugin.help',
                props: {
                    options: [
                        {
                            value: 'start',
                            label: 'Start',
                            labelSnippet: 'plugin.option',
                            description: 'Description',
                            descriptionSnippet: 'plugin.description',
                        },
                    ],
                },
            },
        };

        expect(methods.getPropertyTitle.call(context, property)).toBe('Ausrichtung');
        expect(methods.getPropertyHelpText.call(context, property)).toBe('Hilfe');
        expect(methods.getControlProps.call(context, property)).toEqual({
            options: [
                {
                    value: 'start',
                    label: 'Anfang',
                    labelSnippet: 'plugin.option',
                    description: 'Beschreibung',
                    descriptionSnippet: 'plugin.description',
                },
            ],
        });
        expect(property.adminUI.props.options[0].label).toBe('Start');
    });

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
                        { code: 'invalid_mapping', severity: 'error', key: 'media' },
                        warning,
                        error,
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
                    $te: () => true,
                    getPanelSnippetKey,
                },
                {
                    technicalName: 'spacing',
                },
            ),
        ).toBe('Spacing');
        expect($t).toHaveBeenCalledWith('sw-experience-studio.elements.sw-grid-container.panels.spacing');
    });

    it('uses the technical panel name if its snippet is missing', () => {
        expect(
            methods.getPanelTitle.call(
                {
                    $te: () => false,
                    getPanelSnippetKey: () => 'missing.key',
                },
                { technicalName: 'custom-panel' },
            ),
        ).toBe('custom panel');
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

    it.each([
        {
            name: 'integer',
            property: {
                type: 'integer',
                enum: [
                    1,
                    2,
                    3,
                ],
                adminUI: {
                    component: 'radio-panel',
                },
            },
            optionKey: '2',
            expected: 2,
        },
        {
            name: 'boolean',
            property: {
                type: 'boolean',
                enum: [
                    true,
                    false,
                ],
                adminUI: {
                    component: 'radio-panel',
                },
            },
            optionKey: 'false',
            expected: false,
        },
        {
            name: 'string',
            property: {
                type: 'string',
                enum: [
                    'start',
                    'end',
                ],
                adminUI: {
                    component: 'radio-panel',
                },
            },
            optionKey: 'end',
            expected: 'end',
        },
    ])('emits the $name enum value in its declared type when a radio panel option is selected', (testCase) => {
        const $emit = jest.fn();
        const vm = {
            $emit,
            allowEdit: true,
            getRadioPanelEmitValue: methods.getRadioPanelEmitValue,
            onUpdateField: methods.onUpdateField,
        };

        methods.onUpdateRadioPanelProperty.call(vm, 'columns', testCase.property, testCase.optionKey);

        expect($emit).toHaveBeenCalledTimes(1);
        expect($emit).toHaveBeenCalledWith('update-field', {
            key: 'columns',
            value: testCase.expected,
        });
    });

    it('throws when a radio panel option key matches no enum entry', () => {
        const $emit = jest.fn();
        const vm = {
            $emit,
            allowEdit: true,
            getRadioPanelEmitValue: methods.getRadioPanelEmitValue,
            onUpdateField: methods.onUpdateField,
        };
        const property = {
            type: 'integer',
            enum: [
                1,
                2,
            ],
            adminUI: {
                component: 'radio-panel',
            },
        };

        expect(() => methods.onUpdateRadioPanelProperty.call(vm, 'columns', property, '3')).toThrow(
            'The option "3" of the radio-panel property "columns" matches no enum entry.',
        );
        expect($emit).not.toHaveBeenCalled();
    });

    it('emits the option key for a string radio panel property without an enum', () => {
        const $emit = jest.fn();
        const vm = {
            $emit,
            allowEdit: true,
            getRadioPanelEmitValue: methods.getRadioPanelEmitValue,
            onUpdateField: methods.onUpdateField,
        };
        const property = {
            type: 'string',
            adminUI: {
                component: 'radio-panel',
            },
        };

        methods.onUpdateRadioPanelProperty.call(vm, 'mode', property, 'auto');

        expect($emit).toHaveBeenCalledWith('update-field', {
            key: 'mode',
            value: 'auto',
        });
    });

    it('throws when a non-string radio panel property declares no enum', () => {
        const $emit = jest.fn();
        const vm = {
            $emit,
            allowEdit: true,
            getRadioPanelEmitValue: methods.getRadioPanelEmitValue,
            onUpdateField: methods.onUpdateField,
        };
        const property = {
            type: 'integer',
            adminUI: {
                component: 'radio-panel',
            },
        };

        expect(() => methods.onUpdateRadioPanelProperty.call(vm, 'columns', property, '2')).toThrow(
            'The radio-panel property "columns" of type "integer" declares no enum, so option "2" has no value to emit.',
        );
        expect($emit).not.toHaveBeenCalled();
    });

    it('keeps string option keys for a numeric enum radio panel', () => {
        const options = methods.getRadioPanelOptions.call(
            {
                getControlProps: methods.getControlProps,
            },
            {
                type: 'integer',
                enum: [
                    1,
                    2,
                ],
                adminUI: {
                    component: 'radio-panel',
                },
            },
        ) as Array<{ value: unknown }>;

        expect(options.map((option) => option.value)).toStrictEqual([
            '1',
            '2',
        ]);
    });

    it('emits a numeric enum value from the responsive global radio panel path', () => {
        const $emit = jest.fn();
        const property = {
            type: 'integer',
            enum: [
                1,
                2,
            ],
            default: 1,
            adminUI: {
                component: 'radio-panel',
            },
        };
        const vm = {
            $emit,
            allowEdit: true,
            getControlType: methods.getControlType,
            getRadioPanelEmitValue: methods.getRadioPanelEmitValue,
            isResponsiveViewportMode: () => false,
            normalizeResponsiveValue: methods.normalizeResponsiveValue,
            persistBreakpointAwareValue: methods.persistBreakpointAwareValue,
            onUpdateField: methods.onUpdateField,
        };

        methods.onUpdateResponsiveGlobalProperty.call(
            vm,
            'columns',
            property,
            {
                key: 'columns',
                property,
            },
            methods.getRadioPanelEmitValue.call(vm, 'columns', property, '2'),
        );

        expect($emit).toHaveBeenCalledTimes(1);
        expect($emit).toHaveBeenCalledWith('update-field', {
            key: 'columns',
            value: 2,
        });
    });
});
