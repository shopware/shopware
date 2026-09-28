/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';

async function createWrapper({ props, stubs, provide } = {}) {
    return mount(await wrapTestComponent('sw-tree-item', { sync: true }), {
        attachTo: document.body,
        props: {
            item: {
                data: {
                    id: '1a2b3c',
                },
                children: [],
            },
            ...props,
        },
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-field': true,
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-checkbox-field': true,
                'sw-confirm-field': true,
                ...stubs,
                'sw-tree-item': await wrapTestComponent('sw-tree-item', {
                    sync: true,
                }),
                'sw-vnode-renderer': true,
                'sw-skeleton': true,
            },
            provide: {
                getItems: () => {},
                ...provide,
            },
            directives: {
                tooltip: {
                    beforeMount(el, binding) {
                        el.setAttribute('data-tooltip-message', binding.value.message);
                        el.setAttribute('data-tooltip-disabled', binding.value.disabled);
                    },
                    mounted(el, binding) {
                        el.setAttribute('data-tooltip-message', binding.value.message);
                        el.setAttribute('data-tooltip-disabled', binding.value.disabled);
                    },
                    updated(el, binding) {
                        el.setAttribute('data-tooltip-message', binding.value.message);
                        el.setAttribute('data-tooltip-disabled', binding.value.disabled);
                    },
                },
            },
        },
    });
}

describe('src/app/component/tree/sw-tree-item', () => {
    afterEach(() => {
        jest.useRealTimers();
    });

    it('should move the dragged item into a folder after hovering over it', async () => {
        jest.useFakeTimers();
        const moveDrag = jest.fn();
        const openTreeById = jest.fn();
        const wrapper = await createWrapper({
            props: {
                allowDropIntoFolder: true,
            },
            provide: {
                moveDrag,
                openTreeById,
            },
        });
        const draggedItem = { id: 'dragged' };

        wrapper.vm.onMouseEnter(draggedItem, wrapper.vm.item);
        jest.advanceTimersByTime(1200);

        expect(openTreeById).toHaveBeenCalledWith(wrapper.vm.item.id);
        expect(moveDrag).toHaveBeenLastCalledWith(draggedItem, wrapper.vm.item, true);
    });

    it('should not move the dragged item into a folder after leaving it', async () => {
        jest.useFakeTimers();
        const moveDrag = jest.fn();
        const openTreeById = jest.fn();
        const wrapper = await createWrapper({
            props: {
                allowDropIntoFolder: true,
            },
            provide: {
                moveDrag,
                openTreeById,
            },
        });

        wrapper.vm.onMouseEnter({ id: 'dragged' }, wrapper.vm.item);
        wrapper.vm.onMouseLeave();
        jest.advanceTimersByTime(1200);

        expect(openTreeById).not.toHaveBeenCalled();
        expect(moveDrag).not.toHaveBeenCalledWith(expect.anything(), expect.anything(), true);
    });

    it('should have an enabled context menu', async () => {
        const wrapper = await createWrapper();

        const contextButton = wrapper.get('.sw-tree-item__context_button');

        expect(contextButton.attributes().disabled).toBeUndefined();
    });

    it('should have an disabled context menu', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            disableContextMenu: true,
        });

        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.attributes().disabled).toBeDefined();
    });

    it('should contain the default context menu tooltip text when context menu is disabled', async () => {
        const wrapper = await createWrapper();

        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.attributes()['data-tooltip-message']).toBe('sw-tree.general.actions.actionsDisabledInLanguage');
    });

    it('should contain the custom context menu tooltip text when context menu is disabled', async () => {
        const wrapper = await createWrapper();

        const customTooltipMessage = 'You do not have the rights to edit the tree item.';

        await wrapper.setProps({
            contextMenuTooltipText: customTooltipMessage,
        });

        const contextButton = wrapper.find('.sw-tree-item__context_button');
        expect(contextButton.attributes()['data-tooltip-message']).toBe(customTooltipMessage);
    });

    it('should be able to create new categories', async () => {
        const wrapper = await createWrapper();

        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.find('.sw-tree-item__before-action').attributes().disabled).toBeUndefined();
        expect(contextButton.find('.sw-tree-item__after-action').attributes().disabled).toBeUndefined();
        expect(contextButton.find('.sw-tree-item__sub-action').attributes().disabled).toBeUndefined();
        expect(contextButton.find('.sw-tree-item__without-position-action').exists()).toBeFalsy();
    });

    it('should not be able to create new categories with position', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            allowCreateWithoutPosition: true,
        });

        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.find('.sw-tree-item__before-action').exists()).toBeFalsy();
        expect(contextButton.find('.sw-tree-item__after-action').exists()).toBeFalsy();
        expect(contextButton.find('.sw-tree-item__sub-action').exists()).toBeFalsy();
        expect(contextButton.find('.sw-tree-item__without-position-action').exists()).toBeTruthy();
    });

    it('should be unable to create new categories', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            allowNewCategories: false,
        });

        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.find('.sw-tree-item__before-action').attributes().disabled).toBeDefined();
        expect(contextButton.find('.sw-tree-item__after-action').attributes().disabled).toBeDefined();
        expect(contextButton.find('.sw-tree-item__sub-action').attributes().disabled).toBeDefined();
    });

    it('should be able to delete categories', async () => {
        const wrapper = await createWrapper();

        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.find('.sw-context-menu__group-button-delete').attributes().disabled).toBeUndefined();
    });

    it('should be unable to delete categories', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            allowDeleteCategories: false,
        });
        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.find('.sw-context-menu__group-button-delete').attributes().disabled).toBeDefined();
    });

    it('should not show href attribute', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            allowDeleteCategories: false,
            onChangeRoute: () => {},
        });

        const treeLink = wrapper.find('.tree-link');
        expect(treeLink.attributes().href).toBeFalsy();
    });

    it('should show href attribute', async () => {
        const wrapper = await createWrapper();

        await wrapper.setProps({
            allowDeleteCategories: false,
            onChangeRoute: () => {},
            getItemUrl: (item) => {
                return 'detail/:id'.replace(':id', item.data.id);
            },
        });

        const treeLink = wrapper.find('.tree-link');
        expect(treeLink.attributes().href).not.toBe('detail/1a2b');
        expect(treeLink.attributes().href).toBe('detail/1a2b3c');
    });

    it('should be able to duplicate items', async () => {
        const wrapper = await createWrapper();

        const contextButton = wrapper.find('.sw-tree-item__context_button');

        await wrapper.setProps({
            allowDuplicate: true,
        });

        expect(contextButton.find('.sw-context-menu__duplicate-action').exists()).toBeTruthy();
    });

    it('should be unable to duplicate items', async () => {
        const wrapper = await createWrapper();

        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.find('.sw-context-menu__duplicate-action').exists()).toBeFalsy();
    });

    describe('naming a new element that is already mounted', () => {
        async function createMountedItemInNamingMode() {
            const wrapper = await createWrapper({
                stubs: {
                    'sw-confirm-field': await wrapTestComponent('sw-confirm-field'),
                    'sw-field-error': true,
                },
            });

            await wrapper.setProps({ newElementId: '1a2b3c' });
            await flushPromises();

            return wrapper;
        }

        beforeEach(() => {
            window.HTMLElement.prototype.scrollIntoView = jest.fn();
        });

        afterEach(() => {
            delete window.HTMLElement.prototype.scrollIntoView;
        });

        it('should focus the naming field', async () => {
            const wrapper = await createMountedItemInNamingMode();

            const nameField = wrapper.get('.sw-tree-detail__edit-tree-item input');

            expect(document.activeElement).toBe(nameField.element);
        });

        it('should scroll the naming field into view', async () => {
            await createMountedItemInNamingMode();

            expect(window.HTMLElement.prototype.scrollIntoView).toHaveBeenCalledWith({
                behavior: 'smooth',
                block: 'center',
            });
        });
    });
});
