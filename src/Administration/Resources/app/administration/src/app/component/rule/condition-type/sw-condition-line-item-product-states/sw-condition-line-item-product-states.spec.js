import { shallowMount } from '@vue/test-utils';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';
import 'src/app/component/rule/condition-type/sw-condition-line-item-product-states';
import 'src/app/component/rule/sw-condition-base';
import 'src/app/component/rule/sw-condition-base-line-item';

async function createWrapper(condition = {}) {
    condition.getEntityName = () => 'rule_condition';

    return shallowMount(await Shopware.Component.build('sw-condition-line-item-product-states'), {
        stubs: {
            'sw-condition-type-select': true,
            'sw-condition-operator-select': true,
            'sw-single-select': true,
            'sw-context-button': true,
            'sw-context-menu-item': true,
            'sw-field-error': true,
        },
        provide: {
            conditionDataProviderService: new ConditionDataProviderService(),
            availableTypes: {},
            availableGroups: [],
            childAssociationField: {},
            insertNodeIntoTree: () => ({}),
            removeNodeFromTree: () => ({}),
            createCondition: () => ({}),
            conditionScopes: [],
            unwrapAllLineItemsCondition: () => ({})
        },
        propsData: {
            condition
        }
    });
}

describe('components/rule/condition-type/sw-condition-line-item-product-states', () => {
    it('should be a Vue.js component', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should set product state', async () => {
        const wrapper = await createWrapper({
            value: {
                productState: 'foo',
            },
        });

        wrapper.vm.productState = 'bar';

        expect(wrapper.vm.condition.value.productState).toEqual('bar');
    });
});
