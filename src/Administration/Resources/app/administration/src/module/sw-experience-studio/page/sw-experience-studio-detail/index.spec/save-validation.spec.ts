import detailComponent from '../index';

describe('module/sw-experience-studio/page/sw-experience-studio-detail save validation', () => {
    const component = detailComponent as unknown as {
        methods: Record<string, (...args: unknown[]) => unknown>;
        computed: Record<string, (...args: unknown[]) => unknown>;
    };

    it('only exposes selected element violations after a failed save', () => {
        const violation = {
            code: 'required_property',
            scope: 'binding',
            severity: 'error',
            elementId: 'element-1',
            key: 'mediaItems',
            message: 'Required property is unconfigured',
            candidates: [],
        };
        const vm = {
            selectedElementId: 'element-1',
            diagnostics: [violation],
            showDiagnostics: false,
        };

        expect(component.computed.selectedElementViolations.call(vm)).toEqual([]);

        vm.showDiagnostics = true;

        expect(component.computed.selectedElementViolations.call(vm)).toEqual([violation]);
    });

    it('shows current diagnostics only after save fails', async () => {
        const saveError = new Error('Save failed');
        const diagnoseLayout = jest.fn().mockResolvedValue(undefined);
        const vm = {
            layout: {
                id: 'layout-1',
                name: 'Landing page',
                layout: [],
            },
            allowSave: true,
            layoutRootSource: 'product',
            layoutLoadCriteria: {},
            layoutRepository: {
                save: jest.fn().mockRejectedValue(saveError),
                get: jest.fn(),
            },
            diagnoseLayout,
            showDiagnostics: false,
            isLoading: false,
        };

        await expect(component.methods.onSave.call(vm)).rejects.toBe(saveError);

        expect(diagnoseLayout).toHaveBeenCalledTimes(1);
        expect(vm.showDiagnostics).toBe(true);
        expect(vm.isLoading).toBe(false);
    });
});
