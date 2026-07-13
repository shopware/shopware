import settingsFieldsComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-settings-fields', () => {
    const methods = (
        settingsFieldsComponent as unknown as {
            methods: Record<string, (...args: unknown[]) => unknown>;
        }
    ).methods;

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
