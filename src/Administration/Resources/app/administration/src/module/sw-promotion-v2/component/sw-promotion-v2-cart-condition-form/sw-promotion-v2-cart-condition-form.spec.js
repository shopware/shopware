/**
 * @package buyers-experience
 */
import { mount } from '@vue/test-utils';

Shopware.Service().register('syncService', () => {
    return {
        httpClient: {
            get() {
                return Promise.resolve({});
            },
        },
        getBasicHeaders() {
            return {};
        },
    };
});

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-promotion-v2-cart-condition-form', {
            sync: true,
        }),
        {
            global: {
                stubs: {
                    'sw-container': {
                        template: '<div class="sw-container"><slot></slot></div>',
                    },
                    'sw-switch-field': {
                        template: '<div class="sw-field sw-switch-field"></div>',
                        props: [
                            'value',
                            'disabled',
                        ],
                    },
                    'sw-card': {
                        template: '<div class="sw-card"><slot></slot></div>',
                    },
                    'sw-promotion-rule-select': {
                        template: '<div class="sw-promotion-rule-select"></div>',
                    },
                    'sw-promotion-v2-rule-select': {
                        template: '<div class="sw-promotion-v2-rule-select"></div>',
                        props: ['disabled'],
                    },
                    'sw-context-menu-item': true,
                    'sw-select-field': true,
                    'sw-number-field': true,
                    'sw-button': true,
                },
                provide: {
                    promotionSyncService: {
                        loadPackagers: () => Promise.resolve(),
                        loadSorters: () => Promise.resolve(),
                    },

                    repositoryFactory: {
                        create: () => ({
                            search: () => Promise.resolve([{ id: 'promotionId1' }]),
                        }),
                    },

                    ruleConditionDataProviderService: {},
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
                    useIndividualCodes: true,
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
                    isNew: () => false,
                },
            },
        },
    );
}

describe('src/module/sw-promotion-v2/component/sw-promotion-v2-cart-condition-form', () => {
    it('should have disabled form fields', async () => {
        global.activeAclRoles = [];
        const wrapper = await createWrapper();

        const elements = wrapper.findAllComponents('.sw-field');
        expect(elements.length).toBeGreaterThan(0);
        elements.forEach((el) => expect(el.props('disabled')).toBe(true));

        expect(wrapper.findComponent('.sw-promotion-v2-cart-condition-form__rule-select-cart').props('disabled')).toBe(true);
    });

    it('should not have disabled form fields', async () => {
        global.activeAclRoles = ['promotion.editor'];

        const wrapper = await createWrapper();

        const elements = wrapper.findAllComponents('.sw-field');
        expect(elements.length).toBeGreaterThan(0);
        elements.forEach((el) => expect(el.props('disabled')).toBe(false));

        expect(wrapper.findComponent('.sw-promotion-v2-cart-condition-form__rule-select-cart').props('disabled')).toBe(
            false,
        );
    });

    it('should add conditions association', async () => {
        global.activeAclRoles = [];

        const wrapper = await createWrapper();
        const criteria = wrapper.vm.ruleFilter;

        expect(criteria.associations[0].association).toBe('conditions');
    });
});
