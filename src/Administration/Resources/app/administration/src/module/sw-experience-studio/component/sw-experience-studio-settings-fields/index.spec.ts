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
