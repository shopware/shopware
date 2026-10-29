/**
 * @sw-package fundamentals@after-sales
 */
import { mount, flushPromises } from '@vue/test-utils';
import ConditionDataProviderService from 'src/app/service/rule-condition.service';

type ZipCodeCondition = {
    value?: {
        operator?: string;
        zipCodes?: string[];
    };
};

const createWrapper = async (condition: ZipCodeCondition = {}) => {
    const wrapper = mount(await wrapTestComponent('sw-condition-billing-zip-code', { sync: true }), {
        props: {
            condition,
        },
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-condition-operator-select': await wrapTestComponent('sw-condition-operator-select'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-multi-tag-select': await wrapTestComponent('sw-multi-tag-select'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-select-selection-list': await wrapTestComponent('sw-select-selection-list'),
                'sw-context-button': true,
                'sw-context-menu-item': true,
                'sw-field-error': true,
                'sw-single-select': true,
                'sw-arrow-field': true,
                'sw-condition-type-select': true,
                'sw-label': true,
                'sw-field-copyable': true,
                'sw-inheritance-switch': true,
                'sw-ai-copilot-badge': true,
                'sw-help-text': true,
            },
            provide: {
                conditionDataProviderService: new ConditionDataProviderService(),
                availableTypes: {},
                availableGroups: [],
                restrictedConditions: [],
                childAssociationField: {},
                validationService: {},
            },
        },
    });

    await flushPromises();

    return wrapper;
};

describe('src/app/component/rule/condition-type/sw-condition-billing-zip-code', () => {
    it('should render a numeric zip code in a number field', async () => {
        const wrapper = await createWrapper({
            value: { operator: '>=', zipCodes: ['12345'] },
        });

        expect(wrapper.find('.sw-multi-tag-select').exists()).toBe(false);
        expect(wrapper.get('.mt-number-field input').element).toHaveProperty('value', '12345');
    });

    it('should render alphanumeric zip codes as tags', async () => {
        const wrapper = await createWrapper({
            value: { operator: '=', zipCodes: ['12345', 'AB-1*'] },
        });

        const tags = wrapper.findAll('.sw-multi-tag-select .sw-select-selection-list__item-holder');

        expect(tags).toHaveLength(2);
        expect(tags[0].text()).toBe('12345');
        expect(tags[1].text()).toBe('AB-1*');
    });

    it('should render the tag field without tags when the condition has no value', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.sw-multi-tag-select').exists()).toBe(true);
        expect(wrapper.findAll('.sw-multi-tag-select .sw-select-selection-list__item-holder')).toHaveLength(0);
    });

    it('should add an entered zip code to the condition', async () => {
        const condition: ZipCodeCondition = {};
        const wrapper = await createWrapper(condition);

        const input = wrapper.get('.sw-multi-tag-select .sw-select-selection-list__input');
        await input.setValue('12345');
        await input.trigger('keydown', { key: 'Enter' });
        await flushPromises();

        expect(condition.value?.zipCodes).toEqual(['12345']);
    });
});
