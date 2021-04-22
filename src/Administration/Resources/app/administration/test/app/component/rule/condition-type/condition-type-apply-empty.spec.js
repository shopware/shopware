/* global adminPath */
import { shallowMount } from '@vue/test-utils';
import 'src/app/component/rule/sw-condition-base';
import 'src/app/component/rule/sw-condition-type-select';
import 'src/app/component/rule/sw-condition-operator-select';
import 'src/app/component/context-menu/sw-context-button';
import 'src/app/component/form/select/base/sw-single-select';
import 'src/app/component/form/select/base/sw-select-base';
import 'src/app/component/form/select/base/sw-select-result';
import 'src/app/component/form/select/base/sw-select-result-list';
import 'src/app/component/base/sw-highlight-text';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';
// eslint-disable-next-line
import path from 'path';

const conditionTypesRootPath = 'src/app/component/rule/condition-type/';
const conditionTypesApplyIsEmpty = [
    {
        filePath: 'sw-condition-days-since-last-order',
        value: 'daysPassed'
    },
    {
        filePath: 'sw-condition-billing-country',
        value: 'countryIds'
    },
    {
        filePath: 'sw-condition-billing-street',
        value: 'streetName'
    },
    {
        filePath: 'sw-condition-billing-zip-code',
        value: 'zipCodes'
    },
    {
        filePath: 'sw-condition-customer-tag',
        value: 'identifiers'
    },
    {
        filePath: 'sw-condition-shipping-zip-code',
        value: 'zipCodes'
    },
    {
        filePath: 'sw-condition-last-name',
        value: 'lastName'
    },
    {
        filePath: 'sw-condition-shipping-street',
        value: 'streetName'
    },
    {
        filePath: 'sw-condition-shipping-country',
        value: 'countryIds'
    },
    {
        filePath: 'sw-condition-line-item-tag',
        value: 'identifiers'
    },
    {
        filePath: 'sw-condition-line-item-of-manufacturer',
        value: 'manufacturerIds'
    },
    {
        filePath: 'sw-condition-line-item-release-date',
        value: 'lineItemReleaseDate'
    },
    {
        filePath: 'sw-condition-line-item-in-category',
        value: 'categoryIds'
    },
    {
        filePath: 'sw-condition-line-item-dimension-width',
        value: 'amount'
    },
    {
        filePath: 'sw-condition-line-item-dimension-height',
        value: 'amount'
    },
    {
        filePath: 'sw-condition-line-item-dimension-length',
        value: 'amount'
    },
    {
        filePath: 'sw-condition-line-item-dimension-weight',
        value: 'amount'
    }
];

function importAllConditionTypes() {
    return Promise.all(conditionTypesApplyIsEmpty.map(conditionType => {
        return import(path.join(adminPath, conditionTypesRootPath, conditionType.filePath));
    }));
}

function createWrapperForComponent(componentName, props = {}) {
    return shallowMount(Shopware.Component.build(componentName), {
        stubs: {
            'sw-condition-type-select': Shopware.Component.build('sw-condition-type-select'),
            'sw-condition-operator-select': Shopware.Component.build('sw-condition-operator-select'),
            'sw-context-button': Shopware.Component.build('sw-context-button'),
            'sw-select-result-list': Shopware.Component.build('sw-select-result-list'),
            'sw-single-select': Shopware.Component.build('sw-single-select'),
            'sw-highlight-text': Shopware.Component.build('sw-highlight-text'),
            'sw-select-result': Shopware.Component.build('sw-select-result'),
            'sw-entity-tag-select': true,
            'sw-entity-multi-select': true,
            'sw-condition-base': true,
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
            'sw-datepicker': true
        },
        provide: {
            conditionDataProviderService: new ConditionDataProviderService(),
            availableTypes: [],
            childAssociationField: {},
            repositoryFactory: {
                create: () => ({})
            },
            removeNodeFromTree: () => {}
        },
        propsData: {
            condition: {},
            ...props
        }
    });
}

describe('src/app/component/rule/condition-type/*.js', () => {
    beforeAll(() => {
        return importAllConditionTypes();
    });

    it.each(conditionTypesApplyIsEmpty)('The component %s should be a mounted successfully', async (conditionType) => {
        const wrapper = createWrapperForComponent(conditionType.filePath);
        const operatorSelect = wrapper.get('.sw-condition-operator-select__select');
        await operatorSelect.trigger('click');
        await wrapper.vm.$nextTick();

        const conditionOptions = wrapper.findAll('.sw-select-result');
        expect(conditionOptions.exists()).toBeTruthy();
        // Expect always last option is "Empty"
        expect(conditionOptions.filter(option => option.text() === 'global.sw-condition.operator.empty').length).toBe(1);
    });

    it.each(conditionTypesApplyIsEmpty)('Should be delete value when operator is empty', async (conditionType) => {
        const wrapper = createWrapperForComponent(conditionType.filePath);

        const condition = { value: { operator: '=', [conditionType.value]: 'kyln' } };
        await wrapper.setProps({ condition: condition });
        await wrapper.vm.$nextTick();
        expect(wrapper.vm.condition.value[conditionType.value]).toEqual('kyln');

        const conditionEmpty = { value: { operator: 'empty', [conditionType.value]: 'kyln' } };
        await wrapper.setProps({ condition: conditionEmpty });
        await wrapper.vm.$nextTick();
        // Expect value always delete when operator is empty
        expect(wrapper.vm.condition.value[conditionType.value]).toBeUndefined();
    });
});
