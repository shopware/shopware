import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/tree/sw-tree';
import 'src/app/component/tree/sw-tree-item';
import 'src/app/component/base/sw-icon';

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
            'sw-button': true,
            'sw-context-menu-item': true,
            'sw-context-button': true,
            'icons-small-default-circle-small': true,
            'sw-icon': Shopware.Component.build('sw-icon'),
            'sw-tree-item': Shopware.Component.build('sw-tree-item')
        },
        mocks: {
            $tc: v => v,
            $route: {
                params: [
                    { id: null }
                ]
            }
        },
        provide: {},
        propsData: {
            items: [
                { id: 1, name: 'Example #1', afterId: null, isDeleted: false }
            ]
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

    it('should render item without parentId and childCount correctly', async () => {
        expect(wrapper.find('.icon--small-default-circle-small').exists()).toBeTruthy();
        expect(wrapper.find('.sw-tree-item__label').text()).toEqual('Example #1');

        expect(wrapper.vm.getNewTreeItem({ id: 'myId' }).parentId).toBeNull();
        expect(wrapper.vm.getNewTreeItem({ id: 'myId' }).childCount).toEqual(0);
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
