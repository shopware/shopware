import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/tree/sw-tree-item';

function createWrapper(customOptions = {}) {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {
        bind(el, binding) {
            el.setAttribute('data-tooltip-message', binding.value.message);
            el.setAttribute('data-tooltip-disabled', binding.value.disabled);
        },
        inserted(el, binding) {
            el.setAttribute('data-tooltip-message', binding.value.message);
            el.setAttribute('data-tooltip-disabled', binding.value.disabled);
        },
        update(el, binding) {
            el.setAttribute('data-tooltip-message', binding.value.message);
            el.setAttribute('data-tooltip-disabled', binding.value.disabled);
        }
    });
    localVue.directive('droppable', {});
    localVue.directive('draggable', {});

    return shallowMount(Shopware.Component.build('sw-tree-item'), {
        localVue,
        stubs: {
            'sw-icon': true,
            'sw-field': true,
            'sw-context-button': true,
            'sw-context-menu-item': true,
            'sw-checkbox-field': true
        },
        propsData: {
            item: {
                data: {
                    id: '1a2b3c'
                },
                children: []
            }
        },
        ...customOptions
    });
}

describe('src/app/component/tree/sw-tree-item', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    afterEach(() => {
        if (wrapper) wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have an enabled context menu', async () => {
        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.attributes().disabled).not.toBeDefined();
    });

    it('should have an disabled context menu', async () => {
        await wrapper.setProps({
            disableContextMenu: true
        });

        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.attributes().disabled).toBeDefined();
    });

    it('should contain the default context menu tooltip text when context menu is disabled', async () => {
        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.attributes()['data-tooltip-message']).toBe('sw-tree.general.actions.actionsDisabledInLanguage');
    });

    it('should contain the custom context menu tooltip text when context menu is disabled', async () => {
        const customTooltipMessage = 'You do not have the rights to edit the tree item.';

        await wrapper.setProps({
            contextMenuTooltipText: customTooltipMessage
        });

        const contextButton = wrapper.find('.sw-tree-item__context_button');
        expect(contextButton.attributes()['data-tooltip-message']).toBe(customTooltipMessage);
    });

    it('should be able to create new categories', async () => {
        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.find('.sw-tree-item__before-action').attributes().disabled).toBeUndefined();
        expect(contextButton.find('.sw-tree-item__after-action').attributes().disabled).toBeUndefined();
        expect(contextButton.find('.sw-tree-item__sub-action').attributes().disabled).toBeUndefined();
        expect(contextButton.find('.sw-tree-item__without-position-action').exists()).toBeFalsy();
    });

    it('should not be able to create new categories with position', async () => {
        await wrapper.setProps({
            allowCreateWithoutPosition: true
        });

        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.find('.sw-tree-item__before-action').exists()).toBeFalsy();
        expect(contextButton.find('.sw-tree-item__after-action').exists()).toBeFalsy();
        expect(contextButton.find('.sw-tree-item__sub-action').exists()).toBeFalsy();
        expect(contextButton.find('.sw-tree-item__without-position-action').exists()).toBeTruthy();
    });

    it('should be unable to create new categories', async () => {
        await wrapper.setProps({
            allowNewCategories: false
        });

        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.find('.sw-tree-item__before-action').attributes().disabled).not.toBeUndefined();
        expect(contextButton.find('.sw-tree-item__after-action').attributes().disabled).not.toBeUndefined();
        expect(contextButton.find('.sw-tree-item__sub-action').attributes().disabled).not.toBeUndefined();
    });

    it('should be able to delete categories', async () => {
        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.find('.sw-context-menu__group-button-delete').attributes().disabled).toBeUndefined();
    });

    it('should be unable to delete categories', async () => {
        await wrapper.setProps({
            allowDeleteCategories: false
        });
        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.find('.sw-context-menu__group-button-delete').attributes().disabled).not.toBeUndefined();
    });

    it('should not show href attribute', async () => {
        await wrapper.setProps({
            allowDeleteCategories: false,
            onChangeRoute: () => {}
        });

        const treeLink = wrapper.find('.tree-link');
        expect(treeLink.attributes().href).toBeFalsy();
    });

    it('should show href attribute', async () => {
        await wrapper.setProps({
            allowDeleteCategories: false,
            onChangeRoute: () => {},
            getItemUrl: (item) => {
                return 'detail/:id'.replace(':id', item.data.id);
            }
        });

        const treeLink = wrapper.find('.tree-link');
        expect(treeLink.attributes().href).not.toEqual('detail/1a2b');
        expect(treeLink.attributes().href).toEqual('detail/1a2b3c');
    });

    it('should be able to duplicate items', async () => {
        const contextButton = wrapper.find('.sw-tree-item__context_button');

        await wrapper.setProps({
            allowDuplicate: true
        });

        expect(contextButton.find('.sw-context-menu__duplicate-action').exists()).toBeTruthy();
    });

    it('should be unable to duplicate items', async () => {
        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.find('.sw-context-menu__duplicate-action').exists()).toBeFalsy();
    });
});
