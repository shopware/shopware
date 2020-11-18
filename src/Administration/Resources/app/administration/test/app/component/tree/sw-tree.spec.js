import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/tree/sw-tree';

function createWrapper() {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});
    localVue.directive('droppable', {});
    localVue.directive('draggable', {});

    return shallowMount(Shopware.Component.build('sw-tree'), {
        localVue,
        stubs: {
            'sw-field': true,
            'sw-tree-input-field': true,
            'sw-button': true
        },
        mocks: {
            $tc: v => v
        },
        provide: {},
        propsData: {
            items: []
        }
    });
}

describe('src/app/component/tree/sw-tree', () => {
    let wrapper;

    beforeEach(() => {
        wrapper = createWrapper();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should show the delete button', async () => {
        expect(wrapper.find('.sw-tree-actions__delete_categories').exists()).toBeFalsy();

        await wrapper.setData({
            checkedElementsCount: 2
        });

        expect(wrapper.find('.sw-tree-actions__delete_categories').exists()).toBeTruthy();
    });

    it('should allow to delete the items', async () => {
        expect(wrapper.find('.sw-tree-actions__delete_categories').exists()).toBeFalsy();

        await wrapper.setData({
            checkedElementsCount: 2
        });

        expect(wrapper.find('.sw-tree-actions__delete_categories').attributes().disabled).not.toBeDefined();
    });

    it('should not allow to delete the items', async () => {
        expect(wrapper.find('.sw-tree-actions__delete_categories').exists()).toBeFalsy();

        await wrapper.setProps({
            allowDeleteCategories: false
        });

        await wrapper.setData({
            checkedElementsCount: 2
        });

        expect(wrapper.find('.sw-tree-actions__delete_categories').attributes().disabled).toBeDefined();
    });
});
