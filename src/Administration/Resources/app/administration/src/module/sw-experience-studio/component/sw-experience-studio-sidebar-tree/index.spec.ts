import sidebarTreeComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-sidebar-tree', () => {
    const methods = (sidebarTreeComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> })
        .methods;
    const computed = (sidebarTreeComponent as unknown as { computed: Record<string, (...args: unknown[]) => unknown> })
        .computed;

    it('uses the active layout name for the tree header', () => {
        const vm = {
            layout: { name: 'Basic Listing' },
        };

        expect(computed.panelTitle.call(vm)).toBe('Basic Listing');
    });

    it('falls back to the structure label when no layout name is available', () => {
        const translate = jest.fn().mockReturnValue('Structure');
        const vm = {
            layout: null,
            $t: translate,
        };

        expect(computed.panelTitle.call(vm)).toBe('Structure');
        expect(translate).toHaveBeenCalledWith('sw-experience-studio.detail.sidebarTree.title');
    });

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

    it('emits the add-element trigger as the picker anchor', () => {
        const $emit = jest.fn();
        const trigger = document.createElement('button');
        const vm = {
            $emit,
        };

        methods.onAddRootElement.call(vm, { currentTarget: trigger } as MouseEvent);

        expect($emit).toHaveBeenCalledWith('add-element', {
            parentElementId: null,
            slotName: null,
            anchorElement: trigger,
        });
    });
});
