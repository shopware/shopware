import sidebarTreeNodeComponent from './index';

describe('module/sw-experience-studio/component/sw-experience-studio-sidebar-tree-node', () => {
    const computed = (sidebarTreeNodeComponent as unknown as { computed: Record<string, (...args: unknown[]) => unknown> })
        .computed;
    const methods = (sidebarTreeNodeComponent as unknown as { methods: Record<string, (...args: unknown[]) => unknown> })
        .methods;

    it('uses configured type icon when available', () => {
        const vm = {
            contentElement: {
                component: 'Sw:Content:Text',
            },
            elementTypeStore: {
                getByName: jest.fn().mockReturnValue({
                    icon: 'regular-align-left',
                }),
            },
        };

        expect(computed.typeIcon.call(vm)).toBe('regular-align-left');
    });

    it('falls back to generic icon when no type icon exists', () => {
        const vm = {
            contentElement: {
                component: 'Sw:Content:Unknown',
            },
            elementTypeStore: {
                getByName: jest.fn().mockReturnValue({
                    icon: null,
                }),
            },
        };

        expect(computed.typeIcon.call(vm)).toBe('bars-square-s');
    });

    it('includes defined but currently empty slots in tree entries', () => {
        const vm = {
            contentElement: {
                component: 'Sw:Grid:Container',
                slots: {},
            },
            elementTypeStore: {
                getByName: jest.fn().mockReturnValue({
                    slots: [
                        { name: 'content' },
                    ],
                }),
            },
        };

        expect(computed.slotEntries.call(vm)).toEqual([
            {
                name: 'content',
                elements: [],
            },
        ]);
    });

    it('emits move payload when dropping an element into a slot', () => {
        const $emit = jest.fn();
        const vm = {
            $emit,
        };

        methods.onDropElement.call(
            vm,
            { elementId: 'element-id' },
            { newParentElementId: 'parent-id', newSlotName: 'main', newIndex: 2 },
        );

        expect($emit).toHaveBeenCalledWith('move-element', {
            elementId: 'element-id',
            newParentElementId: 'parent-id',
            newSlotName: 'main',
            newIndex: 2,
        });
    });

    it('marks dropping into dragged subtree as invalid', () => {
        const vm = {
            allowDragAndDrop: true,
            validateMoveTarget: null,
        };

        expect(
            methods.validateMoveDrop.call(
                vm,
                {
                    elementId: 'parent',
                    subtreeIds: [
                        'parent',
                        'child',
                    ],
                },
                { newParentElementId: 'child', newSlotName: 'main', newIndex: 0 },
            ),
        ).toBe(false);
    });
});
