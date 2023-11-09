/* global adminPath */
import { mount } from '@vue/test-utils_v3';
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

async function createWrapperForComponent(componentName) {
    return mount(await wrapTestComponent(componentName, { sync: true }), {
        props: {
            condition: {},
        },
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-condition-type-select': await wrapTestComponent('sw-condition-type-select'),
                'sw-condition-operator-select': await wrapTestComponent('sw-condition-operator-select'),
                'sw-condition-is-net-select': true,
                'sw-context-button': await wrapTestComponent('sw-context-button'),
                'sw-select-result-list': await wrapTestComponent('sw-select-result-list'),
                'sw-single-select': await wrapTestComponent('sw-single-select'),
                'sw-grouped-single-select': await wrapTestComponent('sw-grouped-single-select'),
                'sw-highlight-text': await wrapTestComponent('sw-highlight-text'),
                'sw-select-result': await wrapTestComponent('sw-select-result'),
                'sw-entity-tag-select': true,
                'sw-entity-multi-select': true,
                'sw-condition-base': true,
                'sw-condition-base-line-item': true,
                'sw-tagged-field': true,
                'sw-context-menu-item': true,
                'sw-number-field': true,
                'sw-field-error': true,
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
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
        },
    });
}

describe('src/app/component/rule/condition-type/*.js', () => {
    beforeAll(() => {
        return importAllConditionTypes();
    });

    it.each(conditionTypesApplyIsEmpty)('The component %s should be a mounted successfully', async (conditionType) => {
        const wrapper = await createWrapperForComponent(conditionType.filePath);
        await flushPromises();

        const operatorSelect = wrapper.get('.sw-condition-operator-select__select .sw-select__selection');
        await operatorSelect.trigger('click');
        await flushPromises();

        const conditionOptions = wrapper.findAll('.sw-select-result');

        expect(conditionOptions.length).toBeGreaterThan(0);
        expect(conditionOptions.every((conditionOption) => conditionOption.exists())).toBe(true);
        // Expect always last option is "Empty"
        expect(conditionOptions.filter(option => option.text() === 'global.sw-condition.operator.empty')).toHaveLength(1);
    });

    it.each(conditionTypesApplyIsEmpty)('Should delete value when operator is empty', async (conditionType) => {
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
