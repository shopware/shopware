/* global adminPath */
import { shallowMount } from '@vue/test-utils';
import 'src/app/component/rule/sw-condition-base';
import 'src/app/component/rule/sw-condition-base-line-item';
import 'src/app/component/rule/sw-condition-type-select';
import 'src/app/component/rule/sw-condition-operator-select';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/form/select/base/sw-grouped-single-select';
import 'src/app/component/base/sw-highlight-text';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';
// eslint-disable-next-line
import path from 'path';

const conditionTypesRootPath = 'src/app/component/rule/condition-type/';
const conditionTypesApplyIsEmpty = [
    {
        filePath: 'sw-condition-billing-zip-code',
        value: 'zipCodes',
    },
    {
        filePath: 'sw-condition-shipping-zip-code',
        value: 'zipCodes',
    },
    {
        filePath: 'sw-condition-line-item-in-category',
        value: 'categoryIds',
    },
    {
        filePath: 'sw-condition-line-item-purchase-price',
        value: 'amount',
    },
];

function importAllConditionTypes() {
    return Promise.all(conditionTypesApplyIsEmpty.map(conditionType => {
        return import(path.join(adminPath, conditionTypesRootPath, conditionType.filePath));
    }));
}

async function createWrapperForComponent(componentName, props = {}) {
    return shallowMount(await Shopware.Component.build(componentName), {
        stubs: {
            'sw-condition-type-select': await Shopware.Component.build('sw-condition-type-select'),
            'sw-condition-operator-select': await Shopware.Component.build('sw-condition-operator-select'),
            'sw-condition-is-net-select': true,
            'sw-context-button': await Shopware.Component.build('sw-context-button'),
            'sw-select-result-list': await Shopware.Component.build('sw-select-result-list'),
            'sw-single-select': await Shopware.Component.build('sw-single-select'),
            'sw-grouped-single-select': await Shopware.Component.build('sw-grouped-single-select'),
            'sw-highlight-text': await Shopware.Component.build('sw-highlight-text'),
            'sw-select-result': await Shopware.Component.build('sw-select-result'),
            'sw-entity-tag-select': true,
            'sw-entity-multi-select': true,
            'sw-condition-base': true,
            'sw-condition-base-line-item': true,
            'sw-tagged-field': true,
            'sw-context-menu-item': true,
            'sw-number-field': true,
            'sw-field-error': true,
            'sw-arrow-field': true,
            'sw-select-base': true,
            'sw-block-field': true,
            'sw-text-field': true,
            'sw-icon': true,
            'sw-popover': true,
            'sw-datepicker': true,
        },
        provide: {
            conditionDataProviderService: new ConditionDataProviderService(),
            availableTypes: [],
            availableGroups: [],
            restrictedConditions: [],
            childAssociationField: {},
            repositoryFactory: {
                create: () => ({}),
            },
            insertNodeIntoTree: () => ({}),
            removeNodeFromTree: () => ({}),
            createCondition: () => ({}),
            conditionScopes: [],
            unwrapAllLineItemsCondition: () => ({}),
        },
        propsData: {
            condition: {},
            ...props,
        },
    });
}

describe('src/app/component/rule/condition-type/*.js', () => {
    beforeAll(() => {
        return importAllConditionTypes();
    });

    it.each(conditionTypesApplyIsEmpty)('The component %s should be a mounted successfully', async (conditionType) => {
        const wrapper = await createWrapperForComponent(conditionType.filePath);
        const operatorSelect = wrapper.get('.sw-condition-operator-select__select');
        await operatorSelect.trigger('click');
        await wrapper.vm.$nextTick();

        const conditionOptions = wrapper.findAll('.sw-select-result');
        expect(conditionOptions.exists()).toBeTruthy();
        // Expect always last option is "Empty"
        expect(conditionOptions.filter(option => option.text() === 'global.sw-condition.operator.empty')).toHaveLength(1);
    });

    it.each(conditionTypesApplyIsEmpty)('Should be delete value when operator is empty', async (conditionType) => {
        const wrapper = await createWrapperForComponent(conditionType.filePath);

        const condition = { value: { operator: '=', [conditionType.value]: 'kyln' } };
        await wrapper.setProps({ condition: condition });
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.condition.value[conditionType.value]).toBe('kyln');

        const conditionEmpty = { value: { operator: 'empty', [conditionType.value]: 'kyln' } };
        await wrapper.setProps({ condition: conditionEmpty });
        await wrapper.vm.$nextTick();
        // Expect value always delete when operator is empty
        expect(wrapper.vm.condition.value[conditionType.value]).toBeUndefined();
    });
});
