import { shallowMount } from '@vue/test-utils';
import 'src/app/component/rule/sw-condition-tree';

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
                getConditions: () => {}
            },
            associationField: 'foo',
            associationValue: 'bar',
            ...customProps
        }
    });
}

describe('src/app/component/rule/sw-condition-tree', () => {
    it('should be a Vue.JS component', () => {
        const wrapper = createWrapper();
        wrapper.setData({ conditionTree: {} });

        expect(wrapper.isVueInstance()).toBe(true);
    });

    it('should have enabled condition tree node', () => {
        const wrapper = createWrapper();
        wrapper.setData({ conditionTree: {} });

        const conditionTreeNode = wrapper.find('sw-condition-tree-node-stub');

        expect(conditionTreeNode.attributes().disabled).toBeUndefined();
    });

    it('should have disabled condition type select', () => {
        const wrapper = createWrapper({
            disabled: true
        });
        wrapper.setData({ conditionTree: {} });

        const conditionTreeNode = wrapper.find('sw-condition-tree-node-stub');

        expect(conditionTreeNode.attributes().disabled).toBe('true');
    });
});
