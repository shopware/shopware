import { shallowMount } from '@vue/test-utils';
import 'src/app/component/rule/sw-condition-all-line-items-container';
import 'src/app/component/rule/sw-condition-base';
import 'src/app/component/rule/condition-type/sw-condition-goods-price';

const createCondition = jest.fn();
const insertNodeIntoTree = jest.fn();
const removeNodeFromTree = jest.fn();

async function createWrapper(customProps = {}) {
    return shallowMount(await Shopware.Component.build('sw-condition-all-line-items-container'), {
        stubs: {
            'sw-condition-tree-node': true,
            'sw-condition-base': await Shopware.Component.build('sw-condition-base'),
            'sw-condition-goods-price': await Shopware.Component.build('sw-condition-goods-price'),
        },
        provide: {
            conditionDataProviderService: {
                getPlaceholderData: () => {},
                getByType: () => {
                    return {
                        component: 'sw-condition-goods-price',
                    };
                },
            },
            createCondition,
            insertNodeTree: {},
            insertNodeIntoTree,
            removeNodeFromTree,
            childAssociationField: 'children',
        },
        propsData: {
            parentCondition: {
                id: 'foo',
            },
            condition: {
                type: 'allLineItemsContainer',
                children: {
                    first() {
                        return {
                            type: 'cartLineItemUnitPrice',
                            value: {
                                amount: 12,
                                operator: '<',
                            },
                        };
                    },
                    length: 1,
                },
            },
            level: 0,
            ...customProps,
        },
    });
}

describe('src/app/component/rule/sw-condition-and-container', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have enabled condition tree', async () => {
        const wrapper = await createWrapper();

        const conditionTreeNode = wrapper.find('sw-condition-tree-node-stub');

        expect(conditionTreeNode.attributes().disabled).toBeUndefined();
    });

    it('should have disabled condition tree', async () => {
        const wrapper = await createWrapper({
            disabled: true,
        });

        const conditionTreeNode = wrapper.find('sw-condition-tree-node-stub');

        expect(conditionTreeNode.attributes().disabled).toBe('true');
    });

    it('should removeNodeFromTree when children length becomes 0', async () => {
        const wrapper = await createWrapper();
        const condition = { ...wrapper.props().condition };
        condition.children.length = 0;
        await wrapper.setProps({ condition });

        expect(removeNodeFromTree).toHaveBeenCalled();
    });

    it('should call injections when children type changes to none line item type', async () => {
        const wrapper = await createWrapper();
        const condition = { ...wrapper.props().condition };
        condition.children.first = () => {
            return {
                type: 'cartGoodsPrice',
                value: {
                    amount: 7,
                    operator: '=',
                },
            };
        };
        await wrapper.setProps({ condition });

        expect(removeNodeFromTree).toHaveBeenCalled();
        expect(createCondition).toHaveBeenCalled();
        expect(insertNodeIntoTree).toHaveBeenCalled();
    });
});
