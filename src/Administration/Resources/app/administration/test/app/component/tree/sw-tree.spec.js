import { createLocalVue, shallowMount } from '@vue/test-utils';
import 'src/app/component/tree/sw-tree';
import 'src/app/component/tree/sw-tree-item';
import 'src/app/component/base/sw-icon';
import 'src/app/component/utils/sw-vnode-renderer';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/sw-checkbox-field';
import getTreeItems from './fixtures/treeItems';

function createWrapper() {
    const localVue = createLocalVue();
    localVue.directive('tooltip', {});
    localVue.directive('droppable', {});
    localVue.directive('draggable', {});

    return shallowMount(Shopware.Component.build('sw-tree'), {
        localVue,
        stubs: {
            'sw-field': Shopware.Component.build('sw-field'),
            'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
            'sw-block-field': Shopware.Component.build('sw-block-field'),
            'sw-base-field': Shopware.Component.build('sw-base-field'),
            'sw-checkbox-field': Shopware.Component.build('sw-checkbox-field'),
            'sw-text-field': Shopware.Component.build('sw-text-field'),
            'sw-field-error': true,
            'sw-tree-input-field': true,
            'sw-button': true,
            'sw-context-menu-item': true,
            'sw-context-button': true,
            'icons-small-default-circle-small': true,
            'icons-small-arrow-small-right': true,
            'icons-small-arrow-small-down': true,
            'icons-multicolor-folder-tree': true,
            'icons-multicolor-folder-tree-open': true,
            'icons-default-action-search': true,
            'icons-small-default-checkmark-line-small': true,
            'sw-vnode-renderer': Shopware.Component.build('sw-vnode-renderer'),
            'sw-icon': Shopware.Component.build('sw-icon'),
            'sw-tree-item': Shopware.Component.build('sw-tree-item')
        },
        mocks: {
            $route: {
                params: [
                    { id: null }
                ]
            }
        },
        provide: {
            validationService: {}
        },
        propsData: {
            items: getTreeItems()
        }
    });
}

describe('src/app/component/tree/sw-tree', () => {
    /** @type Wrapper */
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

    it('should render tree correctly with only the main item', async () => {
        const treeItems = wrapper.findAll('.sw-tree-item');
        expect(treeItems.length).toBe(1);

        // parent should be closed
        expect(treeItems.at(0).classes()).not.toContain('is--opened');

        // parent should contain correct name
        expect(treeItems.at(0).find('.sw-tree-item__element').text()).toContain('Home');
    });

    it('should render tree correctly when user open the main item', async () => {
        await wrapper.find('.sw-tree-item .sw-tree-item__toggle').trigger('click');

        // parent should be open
        const openedParent = wrapper.find('.sw-tree-item.is--opened');
        expect(openedParent.isVisible()).toBe(true);

        // parent should contain correct name
        expect(openedParent.find('.sw-tree-item__element').text()).toContain('Home');

        // two children should be visible
        const childrenItems = openedParent.find('.sw-tree-item__children').findAll('.sw-tree-item');
        expect(childrenItems.length).toBe(2);

        // first child should contain correct names
        expect(childrenItems.at(0).text()).toContain('Health & Games');
        expect(childrenItems.at(1).text()).toContain('Shoes');
    });

    it('should render tree correctly when user open the main item and children group', async () => {
        await wrapper.find('.sw-tree-item .sw-tree-item__toggle').trigger('click');

        const openedParent = wrapper.find('.sw-tree-item.is--opened');
        const childrenItems = openedParent.find('.sw-tree-item__children').findAll('.sw-tree-item');

        // open first child of parent
        await childrenItems.at(0).find('.sw-tree-item__toggle').trigger('click');

        // check if all folders and items are correctly opened
        expect(childrenItems.at(0).text()).toContain('Health & Games');
        expect(childrenItems.at(1).text()).toContain('Shoes');

        const healtGamesFolder = childrenItems.at(0);
        const childrenOfHealthGames = healtGamesFolder.find('.sw-tree-item__children').findAll('.sw-tree-item');

        // check if children have correct class
        const childrenOfHealthGamesNames = [
            'Electronics & Games',
            'Clothing & Grocery',
            'Baby, Health & Garden',
            'Automotive',
            'Toys, Health & Music'
        ];

        childrenOfHealthGames.wrappers.forEach((item, index) => {
            expect(item.classes()).toContain('is--no-children');
            expect(item.text()).toContain(childrenOfHealthGamesNames[index]);
        });
    });

    it('should select Automotive and the checkboxes are ticked correctly', async () => {
        await wrapper.find('.sw-tree-item .sw-tree-item__toggle').trigger('click');

        const openedParent = wrapper.find('.sw-tree-item.is--opened');
        const childrenItems = openedParent.find('.sw-tree-item__children').findAll('.sw-tree-item');

        // open first child of parent
        const healthGamesFolder = childrenItems.at(0);
        await healthGamesFolder.find('.sw-tree-item__toggle').trigger('click');

        // find "Automotive" item
        const automotiveItem = healthGamesFolder
            .find('.sw-tree-item__children')
            .findAll('.sw-tree-item')
            .at(3);

        expect(automotiveItem.text()).toContain('Automotive');

        // check checkbox of automotive
        const automotiveCheckbox = automotiveItem.find('.sw-field--checkbox');
        expect(automotiveCheckbox.props('value')).toBe(false);
        await automotiveCheckbox.find('input').trigger('click');
        expect(automotiveCheckbox.props('value')).toBe(true);

        // check if parents contains ghost checkbox
        const healthGamesFolderCheckbox = healthGamesFolder.find('.sw-tree-item__selection .sw-field--checkbox');
        expect(healthGamesFolderCheckbox.classes()).toContain('sw-field__checkbox--ghost');

        const openedParentCheckbox = openedParent.find('.sw-tree-item__selection .sw-field--checkbox');
        expect(openedParentCheckbox.classes()).toContain('sw-field__checkbox--ghost');
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
