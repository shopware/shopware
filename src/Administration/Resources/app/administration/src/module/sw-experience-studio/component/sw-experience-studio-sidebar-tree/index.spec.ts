import sidebarTreeComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-sidebar-tree', () => {
    const methods = (sidebarTreeComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> })
        .methods;

    it('emits move payload when element is dropped in root area', () => {
        const $emit = jest.fn();
        const vm = {
            $emit,
        };
        methods.onRootDrop.call(
            vm,
            { elementId: 'element-id' },
            { newParentElementId: null, newSlotName: null, newIndex: null },
        );

        expect($emit).toHaveBeenCalledWith('move-element', {
            elementId: 'element-id',
            newParentElementId: null,
            newSlotName: null,
            newIndex: null,
        });
    });

    it('uses external validator for root drops', () => {
        const validateMoveTarget = jest.fn().mockReturnValue(false);
        const vm = {
            allowEdit: true,
            validateMoveTarget,
        };

        expect(
            methods.validateMoveDrop.call(
                vm,
                { elementId: 'element-id' },
                { newParentElementId: null, newSlotName: null, newIndex: null },
            ),
        ).toBe(false);
        expect(validateMoveTarget).toHaveBeenCalledWith({
            elementId: 'element-id',
            newParentElementId: null,
            newSlotName: null,
            newIndex: null,
        });
    });
});
