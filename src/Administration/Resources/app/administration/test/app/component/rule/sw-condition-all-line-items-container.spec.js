import { shallowMount } from '@vue/test-utils';
import 'src/app/component/rule/sw-condition-all-line-items-container';
import 'src/app/component/rule/sw-condition-base';
import 'src/app/component/rule/condition-type/sw-condition-day-of-week';

const createCondition = jest.fn();
const insertNodeIntoTree = jest.fn();
const removeNodeFromTree = jest.fn();

function createWrapper(customProps = {}) {
    return shallowMount(Shopware.Component.build('sw-condition-all-line-items-container'), {
        stubs: {
            'sw-condition-tree-node': true,
            'sw-condition-base': Shopware.Component.build('sw-condition-base'),
            'sw-condition-day-of-week': Shopware.Component.build('sw-condition-day-of-week')
        },
        provide: {
            conditionDataProviderService: {
                getPlaceholderData: () => {},
                getByType: () => {
                    return {
                        component: 'sw-condition-day-of-week'
                    };
                }
            },
            createCondition,
            insertNodeTree: {},
            insertNodeIntoTree,
            removeNodeFromTree,
            childAssociationField: 'children'
        },
        propsData: {
            parentCondition: {
                id: 'foo'
            },
            condition: {
                type: 'allLineItemsContainer',
                children: {
                    first() {
                        return {
                            type: 'cartLineItemUnitPrice',
                            value: {
                                amount: 12,
                                operator: '<'
                            }
                        };
                    },
                    length: 1
                }
            },
            level: 0,
            ...customProps
        }
    });
}

describe('src/app/component/rule/sw-condition-and-container', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should have enabled condition tree', async () => {
        const wrapper = createWrapper();

        const conditionTreeNode = wrapper.find('sw-condition-tree-node-stub');

        expect(conditionTreeNode.attributes().disabled).toBeUndefined();
    });

    it('should have disabled condition tree', async () => {
        const wrapper = createWrapper({
            disabled: true
        });

        const conditionTreeNode = wrapper.find('sw-condition-tree-node-stub');

        expect(conditionTreeNode.attributes().disabled).toBe('true');
    });

    it('should removeNodeFromTree when children length becomes 0', async () => {
        const wrapper = createWrapper();
        const condition = { ...wrapper.props().condition };
        condition.children.length = 0;
        await wrapper.setProps({ condition });

        expect(removeNodeFromTree).toHaveBeenCalled();
    });

    it('should call injections when children type changes to none line item type', async () => {
        const wrapper = createWrapper();
        const condition = { ...wrapper.props().condition };
        condition.children.first = () => {
            return {
                type: 'dayOfWeek',
                value: {
                    dayOfTheWeek: 7,
                    operator: '='
                }
            };
        };
        await wrapper.setProps({ condition });

        expect(removeNodeFromTree).toHaveBeenCalled();
        expect(createCondition).toHaveBeenCalled();
        expect(insertNodeIntoTree).toHaveBeenCalled();
    });
});
