import { shallowMount } from '@vue/test-utils';
import 'src/app/component/rule/sw-condition-type-select';

function createWrapper(customProps = {}, customOptions = {}) {
    return shallowMount(Shopware.Component.build('sw-condition-type-select'), {
        stubs: {
            'sw-arrow-field': true,
            'sw-grouped-single-select': true
        },
        provide: {
            removeNodeFromTree: () => {
            },
            conditionDataProviderService: {},
            restrictedConditions: {},
        },
        propsData: {
            condition: {},
            availableTypes: [],
            ...customProps
        },
        ...customOptions
    });
}

describe('src/app/component/rule/sw-condition-type-select', () => {
    it('should be a Vue.JS component', async () => {
        const wrapper = createWrapper();

        expect(wrapper.vm).toBeTruthy();
    });

    it('should have enabled fields', async () => {
        const wrapper = createWrapper();

        const arrowField = wrapper.find('sw-arrow-field-stub');
        const singleSelect = wrapper.find('sw-grouped-single-select-stub');

        expect(arrowField.attributes().disabled).toBeUndefined();
        expect(singleSelect.attributes().disabled).toBeUndefined();
    });

    it('should have disabled fields', async () => {
        const wrapper = createWrapper();
        await wrapper.setProps({
            disabled: true
        });

        const arrowField = wrapper.find('sw-arrow-field-stub');
        const singleSelect = wrapper.find('sw-grouped-single-select-stub');

        expect(arrowField.attributes().disabled).toBe('true');
        expect(singleSelect.attributes().disabled).toBe('true');
    });

    it('should have the right tooltip according to the restriction', async () => {
        const wrapper = createWrapper({}, {
            provide: {
                removeNodeFromTree: () => {
                },
                conditionDataProviderService: {},
                restrictedConditions: {
                    customerBillingCountry: [
                        {
                            associationName: 'customerBillingCountry',
                            snippet: 'sw-customer-billing-country'
                        }
                    ]
                }
            },
        });

        let tooltipConfig = wrapper.vm.getTooltipConfig({
            component: 'sw-condition-billing-country',
            label: 'sw-billing-country-condition',
            scopes: ['checkout'],
            group: 'customer',
            type: 'customerBillingCountry',
        });
        expect(tooltipConfig.disabled).toBeFalsy();

        tooltipConfig = wrapper.vm.getTooltipConfig({
            component: 'sw-condition-email',
            label: 'sw-billing-country-condition',
            scopes: ['checkout'],
            group: 'customer',
            type: 'customerEmail',
        });
        expect(tooltipConfig.disabled).toBeTruthy();
    });
});
