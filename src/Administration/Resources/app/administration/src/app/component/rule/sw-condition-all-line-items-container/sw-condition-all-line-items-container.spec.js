/**
 * @package services-settings
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

const createCondition = jest.fn();
const insertNodeIntoTree = jest.fn();
const removeNodeFromTree = jest.fn();

async function createWrapper(customProps = {}) {
    return mount(
        await wrapTestComponent('sw-condition-all-line-items-container', { sync: true }),
        {
            props: {
                parentCondition: {
                    id: 'foo',
                },
                condition: {
                    type: 'allLineItemsContainer',
                    children: new EntityCollection(
                        '',
                        'rule_condition',
                        Shopware.Context.api,
                        null,
                        [{
                            id: 'rule-condition-id',
                            type: 'cartLineItemUnitPrice',
                            value: {
                                amount: 12,
                                operator: '<',
                            },
                        }],
                    ),
                },
                level: 0,
                ...customProps,
            },
            global: {
                stubs: {
                    'sw-condition-tree-node': true,
                    'sw-condition-base': await wrapTestComponent('sw-condition-base'),
                    'sw-condition-goods-price': await wrapTestComponent('sw-condition-goods-price'),
                },
                provide: {
                    conditionDataProviderService: {
                        getPlaceholderData: () => {
                        },
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
            },
        },
    );
}

describe('src/app/component/rule/sw-condition-and-container', () => {
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
        condition.children = new EntityCollection(
            '',
            'rule_condition',
            Shopware.Context.api,
            null,
            [],
        );
        await wrapper.setProps({ condition });

        expect(removeNodeFromTree).toHaveBeenCalled();
    });

    it('should call injections when children type changes to none line item type', async () => {
        const wrapper = await createWrapper();
        const condition = { ...wrapper.props().condition };
        condition.children = new EntityCollection(
            '',
            'rule_condition',
            Shopware.Context.api,
            null,
            [{
                type: 'cartGoodsPrice',
                value: {
                    amount: 7,
                    operator: '=',
                },
            }],
        );

        await wrapper.setProps({ condition });

        expect(removeNodeFromTree).toHaveBeenCalled();
        expect(createCondition).toHaveBeenCalled();
        expect(insertNodeIntoTree).toHaveBeenCalled();
    });
});
