/**
 * @sw-package checkout
 */
import { mount } from '@vue/test-utils';
import { deprecations } from 'test/_helper_/deprecations';

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
                    'mt-card': {
                        template: '<div class="mt-card"><slot></slot></div>',
                    },
                    'sw-select-rule-create': {
                        template: '<div class="sw-select-rule-create"></div>',
                        props: ['disabled', 'ruleFilter'],
                    },
                    'sw-context-menu-item': true,
                    'sw-select-field': true,
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

        const elements = wrapper.findAllComponents('.mt-switch');
        expect(elements.length).toBeGreaterThan(0);
        elements.forEach((el) => expect(el.props('disabled')).toBe(true));

        expect(wrapper.findComponent('.sw-promotion-v2-cart-condition-form__rule-select-cart').props('disabled')).toBe(true);
    });

    it('should not have disabled form fields', async () => {
        global.activeAclRoles = ['promotion.editor'];

        const wrapper = await createWrapper();

        const elements = wrapper.findAllComponents('.mt-switch');
        expect(elements.length).toBeGreaterThan(0);
        elements.forEach((el) => expect(el.props('disabled')).toBe(false));

        expect(wrapper.findComponent('.sw-promotion-v2-cart-condition-form__rule-select-cart').props('disabled')).toBe(
            false,
        );
    });

    describe('rule filter of the cart rule select', () => {
        afterEach(() => {
            Shopware.Component.getOverrideRegistry().delete('sw-promotion-v2-cart-condition-form');
        });

        // @deprecated tag:v6.8.0 - The test will be removed with the ruleFilter computed.
        it.deprecated('v6.8.0.0')('passes ruleFilter to the rule select without reporting its deprecation', async () => {
            const wrapper = await createWrapper();

            expect(
                wrapper
                    .getComponent('.sw-promotion-v2-cart-condition-form__rule-select-cart')
                    .props('ruleFilter')
                    .hasAssociation('conditions'),
            ).toBe(true);
            expect(deprecations).not.toHaveTriggered('sw-promotion-v2-cart-condition-form.ruleFilter is deprecated');
        });

        // @deprecated tag:v6.8.0 - The test will be removed with the ruleFilter computed.
        it.deprecated('v6.8.0.0')('passes an override of ruleFilter to the rule select', async () => {
            const overriddenCriteria = new Shopware.Data.Criteria(1, 5);
            Shopware.Component.override('sw-promotion-v2-cart-condition-form', {
                computed: {
                    ruleFilter() {
                        return overriddenCriteria;
                    },
                },
            });

            const wrapper = await createWrapper();

            expect(wrapper.getComponent('.sw-promotion-v2-cart-condition-form__rule-select-cart').props('ruleFilter')).toBe(
                overriddenCriteria,
            );
        });

        it.activeFeatureFlags(['v6.8.0.0'])('leaves the rule filter to the default of the rule select', async () => {
            const wrapper = await createWrapper();

            expect(
                wrapper.getComponent('.sw-promotion-v2-cart-condition-form__rule-select-cart').props('ruleFilter'),
            ).toBeUndefined();
        });
    });
});
