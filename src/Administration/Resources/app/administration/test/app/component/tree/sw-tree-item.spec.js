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
            'sw-context-menu-item': true
        },
        mocks: {
            $route: {
                params: {}
            },
            $tc: v => v
        },
        provide: {},
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

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('should have an enabled context menu', () => {
        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.attributes().disabled).not.toBeDefined();
    });

    it('should have an disabled context menu', () => {
        wrapper.setProps({
            disableContextMenu: true
        });

        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.attributes().disabled).toBeDefined();
    });

    it('should contain the default context menu tooltip text when context menu is disabled', () => {
        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.attributes()['data-tooltip-message']).toBe('sw-tree.general.actions.actionsDisabledInLanguage');
    });

    it('should contain the custom context menu tooltip text when context menu is disabled', () => {
        const customTooltipMessage = 'You do not have the rights to edit the tree item.';

        wrapper.setProps({
            contextMenuTooltipText: customTooltipMessage
        });

        const contextButton = wrapper.find('.sw-tree-item__context_button');
        expect(contextButton.attributes()['data-tooltip-message']).toBe(customTooltipMessage);
    });

    it('should be able to create new categories', () => {
        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.find('.sw-tree-item__before-action').attributes().disabled).toBeUndefined();
        expect(contextButton.find('.sw-tree-item__after-action').attributes().disabled).toBeUndefined();
        expect(contextButton.find('.sw-tree-item__sub-action').attributes().disabled).toBeUndefined();
    });

    it('should be unable to create new categories', () => {
        wrapper.setProps({
            allowNewCategories: false
        });

        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.find('.sw-tree-item__before-action').attributes().disabled).not.toBeUndefined();
        expect(contextButton.find('.sw-tree-item__after-action').attributes().disabled).not.toBeUndefined();
        expect(contextButton.find('.sw-tree-item__sub-action').attributes().disabled).not.toBeUndefined();
    });

    it('should be able to delete categories', () => {
        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.find('.sw-context-menu__group-button-delete').attributes().disabled).toBeUndefined();
    });

    it('should be unable to delete categories', () => {
        wrapper.setProps({
            allowDeleteCategories: false
        });
        const contextButton = wrapper.find('.sw-tree-item__context_button');

        expect(contextButton.find('.sw-context-menu__group-button-delete').attributes().disabled).not.toBeUndefined();
    });
});
