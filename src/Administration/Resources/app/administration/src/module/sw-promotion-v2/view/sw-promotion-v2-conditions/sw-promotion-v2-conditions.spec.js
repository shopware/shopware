/**
 * @package buyers-experience
 * @group disabledCompat
 */
import { mount } from '@vue/test-utils';

async function createWrapper() {
    return mount(await wrapTestComponent('sw-promotion-v2-conditions', { sync: true }), {
        global: {
            stubs: {
                'sw-card': {
                    template: '<div class="sw-card"><slot></slot></div>',
                },
                'sw-container': {
                    template: '<div class="sw-container"><slot></slot></div>',
                },
                'sw-text-field': {
                    template: '<input type="text" class="sw-field sw-text-field"></input>',
                    props: ['value', 'disabled'],
                },
                'sw-number-field': {
                    template: '<input type="number" class="sw-field sw-number-field"></input>',
                    props: ['value', 'disabled'],
                },
                'sw-entity-multi-select': {
                    template: '<input type="select" multiple="true" class="sw-field sw-entity-multi-select"></input>',
                    props: ['value', 'disabled'],
                },
                'sw-promotion-v2-sales-channel-select': {
                    template: '<input type="select" class="sw-field sw-promotion-v2-sales-channel-select"></input>',
                    props: ['value', 'disabled'],
                },
                'sw-promotion-v2-rule-select': {
                    template: '<input type="select" class="sw-field sw-promotion-v2-rule-select"></input>',
                    props: ['value', 'disabled'],
                },
                'sw-switch-field': {
                    template: '<input type="checkbox" class="sw-field sw-switch-field"></input>',
                    props: ['value', 'disabled'],
                },
                'sw-promotion-v2-cart-condition-form': true,
            },
            provide: {
                repositoryFactory: {
                    create: () => ({
                        search: () => Promise.resolve([{ id: 'promotionId1' }]),
                    }),
                },
                ruleConditionDataProviderService: {
                    getAwarenessConfigurationByAssignmentName: () => {
                        return {
                            snippet: '',
                        };
                    },
                },
            },
        },
        props: {
            promotion: {
                name: 'Test Promotion',
                active: true,
                validFrom: '2020-07-28T12:00:00.000+00:00',
                validUntil: '2020-08-11T12:00:00.000+00:00',
                maxRedemptionsGlobal: 45,
                maxRedemptionsPerCustomer: 12,
                exclusive: false,
                code: null,
                useCodes: true,
                useIndividualCodes: false,
                individualCodePattern: 'code-%d',
                useSetGroups: false,
                customerRestriction: true,
                orderCount: 0,
                ordersPerCustomerCount: null,
                exclusionIds: ['d671d6d3efc74d2a8b977e3be3cd69c7'],
                translated: {
                    name: 'Test Promotion',
                },
                apiAlias: null,
                id: 'promotionId',
                setgroups: [],
                salesChannels: [
                    {
                        promotionId: 'promotionId',
                        salesChannelId: 'salesChannelId',
                        priority: 1,
                        createdAt: '2020-08-17T13:24:52.692+00:00',
                        id: 'promotionSalesChannelId',
                    },
                ],
                discounts: [],
                individualCodes: [],
                personaRules: [],
                personaCustomers: [],
                orderRules: [],
                cartRules: [],
                translations: [],
                hasOrders: false,
                isNew() {
                    return true;
                },
            },
        },
    });
}

describe('src/module/sw-promotion-v2/component/sw-promotion-v2-conditions', () => {
    it('should disable adding discounts when privileges not set', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();

        wrapper.findAllComponents('.sw-field').forEach((field) => {
            expect(field.props('disabled')).toBe(true);
        });
    });

    it('should enable adding discounts when privilege is set', async () => {
        global.activeAclRoles = ['promotion.editor'];

        const wrapper = await createWrapper();

        wrapper.findAllComponents('.sw-field').forEach((field) => {
            expect(field.props('disabled')).toBeFalsy();
        });
    });
});
