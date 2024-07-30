/**
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

async function createWrapper(customProps = {}, customOptions = {}) {
    return mount(await wrapTestComponent('sw-condition-type-select', { sync: true }), {
        props: {
            condition: {
                promotionAssociation: [
                    {
                        id: 'random-promotion-id',
                    },
                ],
            },
            availableTypes: [],
            ...customProps,
        },
        global: {
            stubs: {
                'sw-grouped-single-select': await wrapTestComponent('sw-grouped-single-select'),
                'sw-single-select': await wrapTestComponent('sw-single-select'),
                'sw-select-base': await wrapTestComponent('sw-select-base'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': true,
                'sw-icon': true,
                'sw-highlight-text': true,
                'sw-select-result': true,
                'sw-select-result-list': true,
            },
            provide: {
                removeNodeFromTree: () => {
                },
                conditionDataProviderService: {},
                restrictedConditions: {},
            },
            ...customOptions,
        },
    });
}

describe('src/app/component/rule/sw-condition-type-select', () => {
    it('should have enabled fields', async () => {
        const wrapper = await createWrapper();

        const singleSelect = wrapper.find('.sw-condition-type-select');

        expect(singleSelect.attributes().disabled).toBeUndefined();
    });

    it('should have disabled fields', async () => {
        const wrapper = await createWrapper();
        await wrapper.setProps({
            disabled: true,
        });

        const singleSelect = wrapper.find('.sw-condition-type-select');

        expect(singleSelect.attributes().disabled).toBe('true');
    });

    it('should have the right tooltip according to the restriction', async () => {
        const wrapper = await createWrapper({}, {
            provide: {
                removeNodeFromTree: () => {
                },
                conditionDataProviderService: {},
                restrictedConditions: {
                    customerBillingCountry: [
                        {
                            associationName: 'customerBillingCountry',
                            snippet: 'sw-customer-billing-country',
                        },
                    ],
                },
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

    it('should remove node from tree if condition has an child association field', async () => {
        const wrapper = await createWrapper({}, {
            provide: {
                removeNodeFromTree: jest.fn(),
                conditionDataProviderService: {},
                restrictedConditions: {},
            },
        });

        // mocking childAssociationField
        wrapper.vm.childAssociationField = 'promotionAssociation';

        await wrapper.vm.changeType('customer');

        expect(wrapper.vm.removeNodeFromTree).toHaveBeenCalledTimes(1);
    });

    it('should get groupAssignments with flow triggers', async () => {
        const wrapper = await createWrapper({}, {
            provide: {
                removeNodeFromTree: () => {
                },
                conditionDataProviderService: {},
                restrictedConditions: {
                    someRestriction: [
                        {
                            associationName: 'flowTrigger.testingFlow',
                        },
                    ],
                },
            },
        });

        expect(wrapper.vm.groupAssignments({
            type: 'someRestriction',
        })).toBe(' sw-restricted-rules.restrictedConditions.relation.flowTrigger');
    });

    it('should get groupAssignments with promotions', async () => {
        const wrapper = await createWrapper({}, {
            provide: {
                removeNodeFromTree: () => {
                },
                conditionDataProviderService: {},
                restrictedConditions: {
                    someRestriction: [
                        {
                            associationName: 'promotion',
                        },
                        {
                            associationName: 'flowTrigger.someFlow',
                        },
                        {
                            associationName: 'flowTrigger.anotherFlow',
                        },
                        {
                            associationName: 'flowTrigger.moreFlows',
                        },
                    ],
                },
            },
        });

        expect(wrapper.vm.groupAssignments({
            type: 'someRestriction',
        })).toBe(' sw-restricted-rules.restrictedConditions.relation.promotion </br> sw-restricted-rules.restrictedConditions.relation.flowTrigger<br />sw-restricted-rules.restrictedConditions.relation.flowTrigger<br />sw-restricted-rules.restrictedConditions.relation.flowTrigger');
    });
});
