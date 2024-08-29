import { mount } from '@vue/test-utils';
import getTreeItems from 'src/app/component/tree/sw-tree/fixtures/treeItems';

/**
 * @package services-settings
 * @group disabledCompat
 */

const { cloneDeep } = Shopware.Utils.object;
const bigItems = getTreeItems();

const items = [
    bigItems[0],
    {
        ...bigItems[1],
        parentId: bigItems[0].id,
    },
];

const defaultProps = {
    items,
    rootParentId: items[0].id,
};

async function createWrapper(props = defaultProps) {
    return mount(
        await wrapTestComponent('sw-settings-rule-tree', { sync: true }),
        {
            props,
            global: {
                stubs: {
                    'sw-tree-item': await wrapTestComponent('sw-tree-item'),
                    'sw-icon': true,
                    'sw-text-field': true,
                    'sw-button': true,
                    'sw-tree-input-field': true,
                    'sw-checkbox-field': true,
                    'sw-confirm-field': true,
                    'sw-context-menu-item': true,
                    'sw-context-button': true,
                    'sw-vnode-renderer': true,
                    'sw-skeleton': true,
                },
            },
        },
    );
}

describe('src/module/sw-settings-rule/view/sw-settings-rule-tree', () => {
    it('should generate tree items when not checked', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.vm.treeItems).toHaveLength(1);
        expect(wrapper.vm.treeItems[0].id).toBe(defaultProps.items[1].id);
        expect(wrapper.vm.treeItems[0].checked).toBe(false);
    });

    it('should generate tree items when checked', async () => {
        const wrapper = await createWrapper();
        await flushPromises();

        expect(wrapper.find('.sw-tree-item').exists()).toBe(true);
        wrapper.getComponent('.sw-tree-item').vm.$emit('check-item', {
            ...defaultProps.items[1],
            checked: true,
        });
        expect(wrapper.vm.checkedElements).toEqual({
            [defaultProps.items[1].id]: defaultProps.items[1].id,
        });

        // trigger watcher
        await wrapper.setProps({
            items: cloneDeep(defaultProps.items),
        });

        expect(wrapper.vm.treeItems).toHaveLength(1);
        expect(wrapper.vm.treeItems[0].id).toBe(defaultProps.items[1].id);
        expect(wrapper.vm.treeItems[0].checked).toBe(true);
    });

    it('should uncheck and delete the item from selection', async () => {
        const wrapper = await createWrapper();
        await flushPromises();
        await wrapper.setData({
            selection: {
                [defaultProps.items[1].id]: defaultProps.items[1],
            },
        });

        expect(wrapper.find('.sw-tree-item').exists()).toBe(true);
        await wrapper.find('.sw-tree-item').trigger('check-item', {
            ...defaultProps.items[1],
            checked: false,
        });

        expect(wrapper.vm.checkedElements).toEqual({});
    });
});
