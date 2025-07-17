/**
 * @sw-package fundamentals@after-sales
 */
import { mount } from '@vue/test-utils';
import EntityCollection from 'src/core/data/entity-collection.data';

const createCondition = jest.fn();
const insertNodeIntoTree = jest.fn();
const removeNodeFromTree = jest.fn();

async function createWrapper(customProps = {}, customProvide = {}, customStubs = {}) {
    return mount(
        await wrapTestComponent('sw-condition-all-line-items-container', {
            sync: true,
        }),
        {
            props: {
                parentCondition: {
                    id: 'foo',
                },
                condition: {
                    type: 'allLineItemsContainer',
                    children: new EntityCollection('', 'rule_condition', Shopware.Context.api, null, [
                        {
                            id: 'rule-condition-id',
                            type: 'cartLineItemUnitPrice',
                            value: {
                                amount: 12,
                                operator: '<',
                            },
                        },
                    ]),
                },
                level: 0,
                ...customProps,
            },
            global: {
                stubs: {
                    'sw-condition-tree-node': true,
                    'sw-condition-base': await wrapTestComponent('sw-condition-base'),
                    'sw-condition-goods-price': await wrapTestComponent('sw-condition-goods-price'),
                    ...customStubs,
                },
                provide: {
                    conditionDataProviderService: {
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
                    ...customProvide,
                },
            },
        },
    );
}

describe('src/app/component/rule/sw-condition-all-line-items-container', () => {
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

    it('should trigger childrenLength watcher when children length becomes 0', async () => {
        const wrapper = await createWrapper();
        const condition = { ...wrapper.props().condition };
        condition.children = new EntityCollection('', 'rule_condition', Shopware.Context.api, null, []);
        await wrapper.setProps({ condition });

        expect(removeNodeFromTree).toHaveBeenCalled();
    });

    it('should call injections when children type changes to none line item type', async () => {
        const wrapper = await createWrapper();
        const condition = { ...wrapper.props().condition };
        condition.children = new EntityCollection('', 'rule_condition', Shopware.Context.api, null, [
            {
                type: 'cartGoodsPrice',
                value: {
                    amount: 7,
                    operator: '=',
                },
            },
        ]);

        await wrapper.setProps({ condition });

        expect(removeNodeFromTree).toHaveBeenCalled();
        expect(createCondition).toHaveBeenCalled();
        expect(insertNodeIntoTree).toHaveBeenCalled();
    });

    it('should remove empty wrapper when children are deleted', async () => {
        const removeNodeFromTreeUi = jest.fn((condition, node) => {
            condition.children = condition.children
                .filter((child) => {
                    return child !== node;
                })
                .map((child, index) => {
                    return { ...child, position: index };
                });
        });

        const wrapper = await createWrapper(
            {
                parentCondition: {
                    id: 'parent-id',
                    type: 'andContainer',
                    children: new EntityCollection('', 'rule_condition', Shopware.Context.api, null, [
                        {
                            id: 'all-line-items-container-id',
                            type: 'allLineItemsContainer',
                            children: new EntityCollection('', 'rule_condition', Shopware.Context.api, null, [
                                {
                                    id: 'child-1',
                                    type: 'cartLineItemInCategory',
                                    value: {
                                        operator: 'oneOf',
                                        categoryIds: ['category-home-id'],
                                    },
                                },
                            ]),
                        },
                    ]),
                },
                condition: {
                    type: 'allLineItemsContainer',
                    children: new EntityCollection('', 'rule_condition', Shopware.Context.api, null, [
                        {
                            id: 'all-line-items-container-id',
                            type: 'cartLineItemInCategory',
                            value: {
                                operator: '=',
                                categoryIds: ['category-id'],
                            },
                            parentId: 'all-line-items-container-id',
                        },
                    ]),
                },
            },
            {
                conditionDataProviderService: {
                    getByType: jest.fn(() => ({
                        component: 'sw-condition-base',
                    })),
                    getComponentByCondition: jest.fn(() => 'sw-condition-base'),
                },
                removeNodeFromTree: removeNodeFromTreeUi,
                availableTypes: [],
                availableGroups: [],
            },
            {
                'sw-condition-tree-node': await wrapTestComponent('sw-condition-tree-node', {
                    sync: true,
                }),
                'sw-condition-base': await wrapTestComponent('sw-condition-base', {
                    sync: true,
                }),
                'sw-context-button': await wrapTestComponent('sw-context-button', {
                    sync: true,
                }),
                'sw-context-menu-item': await wrapTestComponent('sw-context-menu-item', {
                    sync: true,
                }),
                'sw-context-menu': await wrapTestComponent('sw-context-menu', {
                    sync: true,
                }),
                'sw-condition-type-select': true,
                'sw-field-error': true,
                'sw-popover': {
                    template: `
                            <div class="sw-popover sw-context-button__menu-popover">
                                <slot></slot>
                            </div>
                        `,
                },
                'router-link': true,
            },
        );

        await flushPromises();

        const initialConditions = wrapper.findAll(
            '.condition-all-line-items-container .sw-condition .sw-condition__container',
        );
        expect(initialConditions).toHaveLength(1);
        expect(wrapper.vm.childrenLength).toBe(1);

        const contextButton = wrapper.find('.sw-context-button__button');
        expect(contextButton.exists()).toBe(true);

        await contextButton.trigger('click');
        await flushPromises();

        const deleteMenuItem = wrapper.find('.sw-context-menu-item--danger');
        expect(deleteMenuItem.exists()).toBe(true);

        await deleteMenuItem.trigger('click');
        await flushPromises();

        expect(removeNodeFromTreeUi).toHaveBeenCalledTimes(2);

        // first call removes child from container
        const [
            parentCondition1,
            condition1,
        ] = removeNodeFromTreeUi.mock.calls[0];
        expect(parentCondition1.type).toBe('allLineItemsContainer');
        expect(condition1.type).toBe('cartLineItemInCategory');

        // second call removes empty wrapper container
        const [
            parentCondition2,
            condition2,
        ] = removeNodeFromTreeUi.mock.calls[1];
        expect(parentCondition2.type).toBe('andContainer');
        expect(condition2.type).toBe('allLineItemsContainer');

        const remainingConditions = wrapper.findAll(
            '.condition-all-line-items-container .sw-condition .sw-condition__container',
        );
        expect(remainingConditions).toHaveLength(0);
        expect(wrapper.vm.childrenLength).toBe(0);
    });
});
