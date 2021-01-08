import { shallowMount } from '@vue/test-utils';
import 'src/app/component/rule/sw-condition-tree';
import EntityCollection from 'src/core/data-new/entity-collection.data';
import Criteria from 'src/core/data-new/criteria.data';

function createInitialConditionsCollection() {
    return new EntityCollection(null, 'rule_condition', null, new Criteria(), [
        {
            apiAlias: null,
            children: [],
            customFields: null,
            id: 'id1',
            parentId: 'p_id1',
            position: 0,
            ruleId: 'r_id1',
            type: 'customerCustomerGroup',
            updatedAt: null
        }
    ]);
}

function createWrapper(customProps = {}) {
    return shallowMount(Shopware.Component.build('sw-condition-tree'), {
        stubs: {
            'sw-loader': true,
            'sw-condition-tree-node': true
        },
        mocks: {
            $tc: v => v
        },
        propsData: {
            conditionDataProviderService: {
                getConditions: () => {},
                getOrContainerData: () => {}
            },
            conditionRepository: {
                create: () => {
                    return {
                        apiAlias: null,
                        children: [],
                        customFields: null,
                        id: 'id1',
                        parentId: null,
                        position: 0,
                        ruleId: 'r_id1',
                        type: 'orContainer',
                        updatedAt: null,
                        value: {}
                    };
                }
            },
            associationField: 'foo',
            associationValue: 'bar',
            ...customProps
        }
    });
}

describe('src/app/component/rule/sw-condition-tree', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();
        await wrapper.setData({ conditionTree: {} });

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have enabled condition tree node', async () => {
        const wrapper = createWrapper();
        await wrapper.setData({ conditionTree: {} });

        const conditionTreeNode = wrapper.find('sw-condition-tree-node-stub');

        expect(conditionTreeNode.attributes().disabled).toBeUndefined();
    });

    it('should have disabled condition type select', async () => {
        const wrapper = createWrapper({
            disabled: true
        });
        await wrapper.setData({ conditionTree: {} });

        const conditionTreeNode = wrapper.find('sw-condition-tree-node-stub');

        expect(conditionTreeNode.attributes().disabled).toBe('true');
    });

    it('should add root container to initial conditions', async () => {
        const wrapper = createWrapper({
            initialConditions: createInitialConditionsCollection()
        });

        await wrapper.vm.$nextTick();

        expect(wrapper.vm.initialConditions).toEqual(
            expect.arrayContaining([
                expect.objectContaining({
                    type: 'customerCustomerGroup',
                    parentId: 'p_id1'
                }),
                expect.objectContaining({
                    type: 'orContainer',
                    parentId: null
                })
            ])
        );
    });
});
