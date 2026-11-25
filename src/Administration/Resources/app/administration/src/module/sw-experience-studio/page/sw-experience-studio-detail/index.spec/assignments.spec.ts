import detailComponent from '../index';

const methods = (detailComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> }).methods;
const computed = (detailComponent as unknown as { computed: Record<string, (...args: unknown[]) => unknown> }).computed;

describe('Experience Studio layout assignment availability', () => {
    it.each(['product', 'category', 'landing_page'])('offers assignments only for saved %s layouts', (rootSource) => {
        const vm = { getLayoutRootSource: methods.getLayoutRootSource };

        expect(methods.canManageLayoutAssignments.call(vm, { isNew: false, layout: { rootSource } })).toBe(true);
        expect(methods.canManageLayoutAssignments.call(vm, { isNew: true, layout: { rootSource } })).toBe(false);
    });

    it.each([null, 'unknown'])('does not offer assignments for unsupported root source %s', (rootSource) => {
        const vm = { getLayoutRootSource: methods.getLayoutRootSource };

        expect(methods.canManageLayoutAssignments.call(vm, { isNew: false, layout: { rootSource } })).toBe(false);
    });

    it('opens assignment management for the selected layout', () => {
        const vm = {
            assignmentLayoutId: null as string | null,
            isAssignmentModalOpen: false,
        };

        methods.onOpenAssignmentModal.call(vm, 'layout-2');

        expect(vm.assignmentLayoutId).toBe('layout-2');
        expect(vm.isAssignmentModalOpen).toBe(true);
    });

    it('resolves the assignment modal layout independently of the active layout', () => {
        const assignmentLayout = { id: 'layout-2', layout: { rootSource: 'category' } };
        const vm = {
            assignmentLayoutId: 'layout-2',
            assignmentLayout,
            openLayouts: [
                { id: 'layout-1', layout: { rootSource: 'product' } },
                assignmentLayout,
            ],
            getLayoutRootSource: methods.getLayoutRootSource,
        };

        expect(computed.assignmentLayout.call(vm)).toBe(assignmentLayout);
        expect(computed.assignmentRootSource.call(vm)).toBe('category');
    });
});
