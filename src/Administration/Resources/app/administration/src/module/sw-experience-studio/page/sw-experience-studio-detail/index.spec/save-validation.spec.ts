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
});
